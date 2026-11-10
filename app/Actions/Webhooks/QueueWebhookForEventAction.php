<?php

declare(strict_types=1);

namespace App\Actions\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\SendWebhookDeliveryJob;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Collection;

class QueueWebhookForEventAction
{
    /**
     * Broadcast an event to all subscribed endpoints for a tenant.
     *
     * @param  array<string, mixed>  $payload
     * @return Collection<int, WebhookDelivery>
     */
    public function __invoke(Tenant $tenant, WebhookEvent $event, array $payload): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, WebhookEndpoint> $endpoints */
        $endpoints = WebhookEndpoint::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        $deliveries = collect();

        foreach ($endpoints as $endpoint) {
            if (! $endpoint->isSubscribedTo($event)) {
                continue;
            }

            /** @var WebhookDelivery $delivery */
            $delivery = WebhookDelivery::create([
                'tenant_id' => $tenant->id,
                'webhook_endpoint_id' => $endpoint->id,
                'event' => $event->value,
                'payload' => $payload,
                'status' => WebhookDeliveryStatus::Pending,
                'attempts' => 0,
            ]);

            SendWebhookDeliveryJob::dispatch($delivery);
            $deliveries->push($delivery);
        }

        return $deliveries;
    }
}
