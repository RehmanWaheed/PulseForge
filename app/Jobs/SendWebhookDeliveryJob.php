<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Webhooks\DispatchWebhookDeliveryAction;
use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWebhookDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public WebhookDelivery $delivery
    ) {
        $this->onQueue('webhooks');
    }

    /**
     * Execute the job.
     */
    public function handle(DispatchWebhookDeliveryAction $action): void
    {
        $delivered = $action($this->delivery);

        if (! $delivered) {
            $refreshed = $this->delivery->fresh();

            if ($refreshed !== null && $refreshed->status === WebhookDeliveryStatus::Pending) {
                $attemptIndex = max(0, $refreshed->attempts - 1);
                $delaySeconds = DispatchWebhookDeliveryAction::BACKOFF_MATRIX[$attemptIndex] ?? 1800;

                $this->release($delaySeconds);
            }
        }
    }
}
