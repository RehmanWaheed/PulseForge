<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Tenancy\TenantContext;

test('it identifies tenant via X-Tenant-Slug header', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'acme-corp',
        'name' => 'Acme Corporation',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'acme-corp')
        ->getJson('/api/v1/workspace');

    $response->assertOk()
        ->assertJson([
            'id' => $tenant->id,
            'slug' => 'acme-corp',
            'name' => 'Acme Corporation',
        ]);
});

test('it identifies tenant via subdomain', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'cyberdyne',
        'name' => 'Cyberdyne Systems',
    ]);

    $response = $this->getJson('http://cyberdyne.localhost/api/v1/workspace');

    $response->assertOk()
        ->assertJson([
            'id' => $tenant->id,
            'slug' => 'cyberdyne',
        ]);
});

test('it rejects requests with 404 when tenant is unknown', function () {
    $response = $this->withHeader('X-Tenant-Slug', 'non-existent-workspace')
        ->getJson('/api/v1/workspace');

    $response->assertNotFound();
});

test('it rejects requests with 403 when tenant is suspended', function () {
    Tenant::factory()->suspended()->create([
        'slug' => 'defunct-corp',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'defunct-corp')
        ->getJson('/api/v1/workspace');

    $response->assertForbidden();
});

test('it strictly isolates queries across tenants preventing cross-tenant leakage', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

    // Directly insert records for both tenants
    TenantSetting::query()->create([
        'tenant_id' => $tenantA->id,
        'key' => 'alert_webhook',
        'value' => 'https://tenant-a.com/hook',
    ]);

    $settingB = TenantSetting::query()->create([
        'tenant_id' => $tenantB->id,
        'key' => 'alert_webhook',
        'value' => 'https://tenant-b.com/hook',
    ]);

    // Activate Tenant A in context
    $context = app(TenantContext::class);
    $context->set($tenantA);

    // Queries should only see Tenant A's records
    $settings = TenantSetting::all();
    expect($settings)->toHaveCount(1)
        ->and($settings->first()->value)->toBe('https://tenant-a.com/hook');

    // Tenant A cannot retrieve Tenant B's record by primary key
    expect(TenantSetting::find($settingB->id))->toBeNull();

    // Reset context
    $context->clear();
});

test('it automatically stamps tenant_id on model creation when context is active', function () {
    $tenant = Tenant::factory()->create(['slug' => 'auto-stamp-corp']);

    $context = app(TenantContext::class);
    $context->set($tenant);

    $setting = TenantSetting::create([
        'key' => 'theme_mode',
        'value' => 'dark',
    ]);

    expect($setting->tenant_id)->toBe($tenant->id)
        ->and($setting->fresh()->tenant_id)->toBe($tenant->id);

    $context->clear();
});

test('it throws RuntimeException if creating tenant-scoped model without tenant context', function () {
    $context = app(TenantContext::class);
    $context->clear();

    expect(function () {
        TenantSetting::create([
            'key' => 'unbound_key',
            'value' => 'danger',
        ]);
    })->toThrow(RuntimeException::class, 'Cannot create ['.TenantSetting::class.'] without an active TenantContext.');
});

test('it prevents reassigning tenant_id on existing records', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-original']);
    $tenantB = Tenant::factory()->create(['slug' => 'tenant-attacker']);

    $context = app(TenantContext::class);
    $context->set($tenantA);

    $setting = TenantSetting::create([
        'key' => 'secure_config',
        'value' => 'secret_val',
    ]);

    expect(function () use ($setting, $tenantB) {
        $setting->tenant_id = $tenantB->id;
        $setting->save();
    })->toThrow(RuntimeException::class, 'Reassigning tenant_id on ['.TenantSetting::class.'] is strictly forbidden.');

    $context->clear();
});

test('it allows system maintenance queries to bypass tenancy via withoutTenancy macro', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    TenantSetting::query()->create(['tenant_id' => $tenantA->id, 'key' => 'k1', 'value' => 'v1']);
    TenantSetting::query()->create(['tenant_id' => $tenantB->id, 'key' => 'k2', 'value' => 'v2']);

    $context = app(TenantContext::class);
    $context->set($tenantA);

    // Regular query sees 1
    expect(TenantSetting::count())->toBe(1);

    // withoutTenancy macro sees both
    expect(TenantSetting::withoutTenancy()->count())->toBe(2);

    $context->clear();
});
