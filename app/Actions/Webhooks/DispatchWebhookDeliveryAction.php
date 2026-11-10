<?php

declare(strict_types=1);

namespace App\Actions\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookSigner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DispatchWebhookDeliveryAction
{
    /**
     * Exponential backoff retry matrix in seconds.
     *
     * @var array<int, int>
     */
    public const array BACKOFF_MATRIX = [10, 60, 300, 1800];

    public function __construct(
        protected WebhookSigner $signer
    ) {}

    /**
     * Dispatch an outbound webhook delivery with atomic deduplication and HMAC signing.
     */
    public function __invoke(WebhookDelivery $delivery): bool
    {
        // 1. Race Condition Defense #3: Atomic claim transition
        $affected = WebhookDelivery::query()
            ->where('id', $delivery->id)
            ->where('status', WebhookDeliveryStatus::Pending->value)
            ->update([
                'status' => WebhookDeliveryStatus::InFlight->value,
                'last_attempt_at' => now(),
                'attempts' => DB::raw('attempts + 1'),
            ]);

        // If 0 rows affected, another concurrent worker has claimed or processed this delivery
        if ($affected === 0) {
            return false;
        }

        $refreshedDelivery = $delivery->fresh(['endpoint']);

        if ($refreshedDelivery === null || $refreshedDelivery->endpoint === null) {
            return false;
        }

        $endpoint = $refreshedDelivery->endpoint;
        $jsonPayload = json_encode($refreshedDelivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = $this->signer->sign($jsonPayload, $endpoint->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'PulseForge-Webhooks/1.0',
                    'X-PulseForge-Signature' => 'sha256='.$signature,
                    'X-PulseForge-Event' => $refreshedDelivery->event,
                    'X-PulseForge-Delivery' => (string) $refreshedDelivery->id,
                ])
                ->withBody($jsonPayload, 'application/json')
                ->post($endpoint->url);

            if ($response->successful()) {
                $refreshedDelivery->update([
                    'status' => WebhookDeliveryStatus::Delivered,
                    'response_status_code' => $response->status(),
                    'response_body' => Str::limit($response->body(), 1000),
                    'error_message' => null,
                ]);

                return true;
            }

            $this->handleFailure(
                $refreshedDelivery,
                $response->status(),
                Str::limit($response->body(), 1000),
                sprintf('HTTP %d response from subscriber endpoint.', $response->status())
            );

            return false;
        } catch (Throwable $e) {
            $this->handleFailure(
                $refreshedDelivery,
                null,
                null,
                $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Evaluate retry backoff vs final failure.
     */
    protected function handleFailure(
        WebhookDelivery $delivery,
        ?int $statusCode,
        ?string $responseBody,
        string $errorMessage
    ): void {
        $hasMoreAttempts = $delivery->attempts < count(self::BACKOFF_MATRIX);

        $delivery->update([
            'status' => $hasMoreAttempts ? WebhookDeliveryStatus::Pending : WebhookDeliveryStatus::Failed,
            'response_status_code' => $statusCode,
            'response_body' => $responseBody,
            'error_message' => $errorMessage,
        ]);
    }
}
