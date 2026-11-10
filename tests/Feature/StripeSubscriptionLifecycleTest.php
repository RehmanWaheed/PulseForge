<?php

declare(strict_types=1);

use App\Enums\TenantPlan;
use App\Models\Tenant;

test('it processes customer.subscription.created and upgrades tenant plan and quota', function () {
    $tenant = Tenant::factory()->create([
        'plan' => TenantPlan::Free,
        'monitor_limit' => 5,
        'stripe_customer_id' => 'cus_test_123',
    ]);

    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'id' => 'sub_active_pro_456',
                'customer' => 'cus_test_123',
                'status' => 'active',
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'plan' => 'pro',
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/webhooks/stripe', $payload);

    $response->assertOk()
        ->assertJson(['received' => true]);

    $refreshed = $tenant->fresh();
    expect($refreshed->plan)->toBe(TenantPlan::Pro)
        ->and($refreshed->monitor_limit)->toBe(50)
        ->and($refreshed->stripe_subscription_id)->toBe('sub_active_pro_456');
});

test('it processes customer.subscription.deleted and downgrades tenant to free tier', function () {
    $tenant = Tenant::factory()->pro()->create([
        'stripe_customer_id' => 'cus_test_cancel_789',
        'stripe_subscription_id' => 'sub_cancel_789',
    ]);

    $payload = [
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'id' => 'sub_cancel_789',
                'customer' => 'cus_test_cancel_789',
                'status' => 'canceled',
                'metadata' => [
                    'tenant_id' => $tenant->id,
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/webhooks/stripe', $payload);

    $response->assertOk();

    $refreshed = $tenant->fresh();
    expect($refreshed->plan)->toBe(TenantPlan::Free)
        ->and($refreshed->monitor_limit)->toBe(5)
        ->and($refreshed->stripe_subscription_id)->toBeNull();
});
