<?php

declare(strict_types=1);

use App\Actions\Monitors\PerformMonitorCheckAction;
use App\Enums\IncidentStatus;
use App\Enums\MonitorMethod;
use App\Enums\MonitorStatus;
use App\Jobs\CheckMonitorJob;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('it records successful check result when HTTP status matches expected code', function () {
    Http::fake([
        'https://status.example.com/health' => Http::response('{"status":"ok"}', 200),
    ]);

    $tenant = Tenant::factory()->create();
    $monitor = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://status.example.com/health',
        'method' => MonitorMethod::Get,
        'expected_status_code' => 200,
        'timeout_seconds' => 5,
    ]);

    $action = app(PerformMonitorCheckAction::class);
    $result = $action($monitor);

    expect($result->isSuccessful)->toBeTrue()
        ->and($result->statusCode)->toBe(200)
        ->and($result->errorMessage)->toBeNull()
        ->and($result->responseTimeMs)->toBeGreaterThanOrEqual(0);
});

test('it records failure check result when HTTP status code mismatches', function () {
    Http::fake([
        'https://status.example.com/api' => Http::response('Server Down', 503),
    ]);

    $tenant = Tenant::factory()->create();
    $monitor = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://status.example.com/api',
        'expected_status_code' => 200,
    ]);

    $action = app(PerformMonitorCheckAction::class);
    $result = $action($monitor);

    expect($result->isSuccessful)->toBeFalse()
        ->and($result->statusCode)->toBe(503)
        ->and($result->errorMessage)->toContain('Expected HTTP status 200, but received 503.');
});

test('it records failure check result on connection timeout', function () {
    Http::fake([
        'https://slow-service.example.com' => function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 5000 milliseconds');
        },
    ]);

    $tenant = Tenant::factory()->create();
    $monitor = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://slow-service.example.com',
    ]);

    $action = app(PerformMonitorCheckAction::class);
    $result = $action($monitor);

    expect($result->isSuccessful)->toBeFalse()
        ->and($result->statusCode)->toBeNull()
        ->and($result->errorMessage)->toContain('cURL error 28: Operation timed out');
});

test('CheckMonitorJob processes health check and coordinates incident lifecycle', function () {
    Http::fake([
        'https://target.example.com' => Http::response('Outage', 500),
    ]);

    $tenant = Tenant::factory()->create();
    $monitor = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://target.example.com',
        'consecutive_failure_threshold' => 1,
        'consecutive_failures' => 0,
        'status' => MonitorStatus::Healthy,
    ]);

    // Dispatch and execute job synchronously
    CheckMonitorJob::dispatchSync($monitor);

    expect($monitor->fresh()->status)->toBe(MonitorStatus::Down)
        ->and($monitor->fresh()->consecutive_failures)->toBe(1)
        ->and($monitor->fresh()->checkResults)->toHaveCount(1)
        ->and($monitor->fresh()->checkResults->first()->is_successful)->toBeFalse()
        ->and(Incident::count())->toBe(1)
        ->and(Incident::first()->status)->toBe(IncidentStatus::Investigating);
});

test('monitors:dispatch-checks queues jobs exclusively for due monitors', function () {
    Queue::fake();

    $tenant = Tenant::factory()->create();

    // 1. Never checked (due)
    $monitorNeverChecked = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'check_interval_seconds' => 60,
        'last_checked_at' => null,
    ]);

    // 2. Checked 2 minutes ago with 60s interval (due)
    $monitorElapsed = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'check_interval_seconds' => 60,
        'last_checked_at' => now()->subMinutes(2),
    ]);

    // 3. Checked 10 seconds ago with 60s interval (NOT due)
    $monitorFresh = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'check_interval_seconds' => 60,
        'last_checked_at' => now()->subSeconds(10),
    ]);

    // 4. Inactive monitor (NOT due)
    $monitorInactive = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => false,
        'check_interval_seconds' => 60,
        'last_checked_at' => null,
    ]);

    $this->artisan('monitors:dispatch-checks')
        ->expectsOutput('Dispatched health checks for 2 due monitor(s).')
        ->assertSuccessful();

    Queue::assertPushed(CheckMonitorJob::class, function (CheckMonitorJob $job) use ($monitorNeverChecked) {
        return $job->monitor->id === $monitorNeverChecked->id;
    });

    Queue::assertPushed(CheckMonitorJob::class, function (CheckMonitorJob $job) use ($monitorElapsed) {
        return $job->monitor->id === $monitorElapsed->id;
    });

    Queue::assertNotPushed(CheckMonitorJob::class, function (CheckMonitorJob $job) use ($monitorFresh) {
        return $job->monitor->id === $monitorFresh->id;
    });

    Queue::assertNotPushed(CheckMonitorJob::class, function (CheckMonitorJob $job) use ($monitorInactive) {
        return $job->monitor->id === $monitorInactive->id;
    });
});
