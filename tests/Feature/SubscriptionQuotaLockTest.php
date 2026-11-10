<?php

declare(strict_types=1);

use App\Actions\Monitors\CreateMonitorAction;
use App\DTOs\CreateMonitorDTO;
use App\Exceptions\SubscriptionQuotaExceededException;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;

test('it creates monitor within subscription quota limits', function () {
    $tenant = Tenant::factory()->create([
        'monitor_limit' => 2,
    ]);

    $context = app(TenantContext::class);
    $context->set($tenant);

    $action = app(CreateMonitorAction::class);

    $dto1 = new CreateMonitorDTO(name: 'API 1', url: 'https://api1.example.com');
    $monitor1 = $action($tenant, $dto1);

    expect($monitor1)->not->toBeNull()
        ->and($monitor1->tenant_id)->toBe($tenant->id)
        ->and($monitor1->name)->toBe('API 1');

    $dto2 = new CreateMonitorDTO(name: 'API 2', url: 'https://api2.example.com');
    $monitor2 = $action($tenant, $dto2);

    expect($monitor2)->not->toBeNull()
        ->and($tenant->monitors()->count())->toBe(2);

    $context->clear();
});

test('it prevents quota bypass and throws SubscriptionQuotaExceededException when limit reached', function () {
    $tenant = Tenant::factory()->create([
        'monitor_limit' => 2,
    ]);

    $context = app(TenantContext::class);
    $context->set($tenant);

    $action = app(CreateMonitorAction::class);

    $action($tenant, new CreateMonitorDTO(name: 'API 1', url: 'https://api1.example.com'));
    $action($tenant, new CreateMonitorDTO(name: 'API 2', url: 'https://api2.example.com'));

    // Attempt to exceed quota
    expect(function () use ($action, $tenant) {
        $action($tenant, new CreateMonitorDTO(name: 'API 3', url: 'https://api3.example.com'));
    })->toThrow(
        SubscriptionQuotaExceededException::class,
        'Your current subscription plan allows a maximum of 2 monitors. Please upgrade to add more.'
    );

    expect($tenant->monitors()->count())->toBe(2);

    $context->clear();
});
