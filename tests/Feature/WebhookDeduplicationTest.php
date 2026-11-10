<?php

declare(strict_types=1);

use App\Actions\Webhooks\DispatchWebhookDeliveryAction;
use App\Actions\Webhooks\QueueWebhookForEventAction;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\SendWebhookDeliveryJob;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookSigner;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('it prevents duplicate webhook dispatch during worker stampedes via atomic state transitions', function () {
    Http::fake([
        'https://subscriber.example.com/webhook' => Http::response('OK', 200),
    ]);

    $tenant = Tenant::factory()->create();
    $endpoint = WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://subscriber.example.com/webhook',
    ]);

    $delivery = WebhookDelivery::factory()->create([
        'tenant_id' => $tenant->id,
        'webhook_endpoint_id' => $endpoint->id,
        'status' => WebhookDeliveryStatus::Pending,
        'attempts' => 0,
    ]);

    $action = app(DispatchWebhookDeliveryAction::class);

    // Worker 1 claims and delivers the webhook
    $worker1Dispatched = $action($delivery);
    expect($worker1Dispatched)->toBeTrue()
        ->and($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::Delivered)
        ->and($delivery->fresh()->attempts)->toBe(1);

    // Concurrent Worker 2 attempts to deliver the same record (already in_flight or delivered)
    $worker2Dispatched = $action($delivery);
    expect($worker2Dispatched)->toBeFalse();

    // Verify only ONE HTTP POST request was made to the subscriber
    Http::assertSentCount(1);
});

test('it attaches HMAC SHA-256 signature and metadata headers to outbound requests', function () {
    Http::fake([
        'https://customer.test/api/webhooks' => Http::response('{"received":true}', 200),
    ]);

    $tenant = Tenant::factory()->create();
    $endpoint = WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://customer.test/api/webhooks',
        'secret' => 'whsec_my_super_secret_test_key_99',
    ]);

    $delivery = WebhookDelivery::factory()->create([
        'tenant_id' => $tenant->id,
        'webhook_endpoint_id' => $endpoint->id,
        'event' => WebhookEvent::IncidentOpened->value,
        'payload' => ['incident' => 'outage_detected', 'severity' => 'major'],
        'status' => WebhookDeliveryStatus::Pending,
    ]);

    $action = app(DispatchWebhookDeliveryAction::class);
    $action($delivery);

    $signer = app(WebhookSigner::class);
    $expectedPayloadJson = json_encode(['incident' => 'outage_detected', 'severity' => 'major'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $expectedSignature = 'sha256='.$signer->sign($expectedPayloadJson, $endpoint->secret);

    Http::assertSent(function (Request $request) use ($expectedSignature, $delivery) {
        return $request->hasHeader('X-PulseForge-Signature', $expectedSignature)
            && $request->hasHeader('X-PulseForge-Event', WebhookEvent::IncidentOpened->value)
            && $request->hasHeader('X-PulseForge-Delivery', (string) $delivery->id)
            && $request->header('Content-Type')[0] === 'application/json';
    });
});

test('it records failure and increments attempts when subscriber returns server error', function () {
    Http::fake([
        'https://subscriber.example.com/webhook' => Http::response('Internal Server Error', 500),
    ]);

    $tenant = Tenant::factory()->create();
    $endpoint = WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://subscriber.example.com/webhook',
    ]);
    $delivery = WebhookDelivery::factory()->create([
        'tenant_id' => $tenant->id,
        'webhook_endpoint_id' => $endpoint->id,
        'attempts' => 0,
        'status' => WebhookDeliveryStatus::Pending,
    ]);

    $action = app(DispatchWebhookDeliveryAction::class);
    $result = $action($delivery);

    expect($result)->toBeFalse()
        ->and($delivery->fresh()->attempts)->toBe(1)
        ->and($delivery->fresh()->response_status_code)->toBe(500)
        ->and($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::Pending); // Pending retry
});

test('it transitions delivery to failed when maximum retry attempts are exhausted', function () {
    Http::fake([
        'https://subscriber.example.com/webhook' => Http::response('Service Unavailable', 503),
    ]);

    $tenant = Tenant::factory()->create();
    $endpoint = WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://subscriber.example.com/webhook',
    ]);
    $delivery = WebhookDelivery::factory()->create([
        'tenant_id' => $tenant->id,
        'webhook_endpoint_id' => $endpoint->id,
        'attempts' => 3, // Current attempt 4 will exhaust BACKOFF_MATRIX (4 items)
        'status' => WebhookDeliveryStatus::Pending,
    ]);

    $action = app(DispatchWebhookDeliveryAction::class);
    $result = $action($delivery);

    expect($result)->toBeFalse()
        ->and($delivery->fresh()->attempts)->toBe(4)
        ->and($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::Failed);
});

test('QueueWebhookForEventAction dispatches deliveries only to active subscribed endpoints', function () {
    Queue::fake();

    $tenant = Tenant::factory()->create();

    // 1. Subscribed to all events
    $endpointWildcard = WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'events' => ['*'],
        'is_active' => true,
    ]);

    // 2. Subscribed specifically to IncidentOpened
    $endpointSpecific = WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'events' => [WebhookEvent::IncidentOpened->value],
        'is_active' => true,
    ]);

    // 3. Subscribed to IncidentResolved only (should NOT receive IncidentOpened)
    $endpointOther = WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'events' => [WebhookEvent::IncidentResolved->value],
        'is_active' => true,
    ]);

    // 4. Inactive endpoint (should NOT receive)
    $endpointInactive = WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'events' => ['*'],
        'is_active' => false,
    ]);

    $action = app(QueueWebhookForEventAction::class);
    $deliveries = $action($tenant, WebhookEvent::IncidentOpened, ['incident_id' => 999]);

    expect($deliveries)->toHaveCount(2);

    Queue::assertPushed(SendWebhookDeliveryJob::class, 2);
});
