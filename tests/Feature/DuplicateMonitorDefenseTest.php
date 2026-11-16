<?php

declare(strict_types=1);

use App\Actions\Monitors\CreateMonitorAction;
use App\DTOs\CreateMonitorDTO;
use App\Exceptions\DuplicateMonitorException;
use App\Models\Monitor;
use App\Models\Tenant;

test('API rejects monitor creation with duplicate URL within same tenant', function () {
    $tenant = Tenant::factory()->create();

    Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Original Monitor',
        'url' => 'https://api.example.com',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', $tenant->slug)
        ->postJson('/api/v1/monitors', [
            'name' => 'Different Name',
            'url' => 'https://api.example.com',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'url' => 'A monitor for this target URL already exists in your workspace.',
        ]);
});

test('API rejects monitor creation with duplicate Name within same tenant', function () {
    $tenant = Tenant::factory()->create();

    Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Payment Gateway',
        'url' => 'https://payments-v1.example.com',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', $tenant->slug)
        ->postJson('/api/v1/monitors', [
            'name' => 'Payment Gateway',
            'url' => 'https://payments-v2.example.com',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'name' => 'A monitor with this name already exists in your workspace.',
        ]);
});

test('different tenants can monitor identical URLs and names without collision', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

    // Tenant A creates a monitor
    $responseA = $this->withHeader('X-Tenant-Slug', $tenantA->slug)
        ->postJson('/api/v1/monitors', [
            'name' => 'Shared Target',
            'url' => 'https://shared-api.example.com',
        ]);

    $responseA->assertStatus(201);

    // Tenant B creates an identical monitor without conflict
    $responseB = $this->withHeader('X-Tenant-Slug', $tenantB->slug)
        ->postJson('/api/v1/monitors', [
            'name' => 'Shared Target',
            'url' => 'https://shared-api.example.com',
        ]);

    $responseB->assertStatus(201);
});

test('CreateMonitorAction throws DuplicateMonitorException on duplicate URL', function () {
    $tenant = Tenant::factory()->create();
    $action = app(CreateMonitorAction::class);

    $action($tenant, new CreateMonitorDTO(
        name: 'Service 1',
        url: 'https://service.example.com'
    ));

    expect(fn () => $action($tenant, new CreateMonitorDTO(
        name: 'Service 2',
        url: 'https://service.example.com'
    )))->toThrow(DuplicateMonitorException::class, 'A monitor for URL [https://service.example.com] already exists in this workspace.');
});

test('CreateMonitorAction throws DuplicateMonitorException on duplicate Name', function () {
    $tenant = Tenant::factory()->create();
    $action = app(CreateMonitorAction::class);

    $action($tenant, new CreateMonitorDTO(
        name: 'Unique Service',
        url: 'https://service-a.example.com'
    ));

    expect(fn () => $action($tenant, new CreateMonitorDTO(
        name: 'Unique Service',
        url: 'https://service-b.example.com'
    )))->toThrow(DuplicateMonitorException::class, 'A monitor with name [Unique Service] already exists in this workspace.');
});
