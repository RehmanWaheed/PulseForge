<?php

declare(strict_types=1);

use App\Actions\Incidents\ProcessMonitorFailureAction;
use App\Actions\Incidents\ProcessMonitorRecoveryAction;
use App\DTOs\MonitorCheckResultDTO;
use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;

test('it suppresses incident creation until consecutive failure threshold is reached', function () {
    $tenant = Tenant::factory()->create();
    $context = app(TenantContext::class);
    $context->set($tenant);

    $monitor = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'consecutive_failure_threshold' => 3,
        'consecutive_failures' => 0,
        'status' => MonitorStatus::Healthy,
    ]);

    $action = app(ProcessMonitorFailureAction::class);
    $dto = MonitorCheckResultDTO::failure($monitor->id, 'Connection timed out', 504);

    // Attempt 1: consecutive = 1
    $incident1 = $action($monitor, $dto);
    expect($incident1)->toBeNull()
        ->and($monitor->fresh()->consecutive_failures)->toBe(1)
        ->and($monitor->fresh()->status)->toBe(MonitorStatus::Healthy)
        ->and(Incident::count())->toBe(0);

    // Attempt 2: consecutive = 2
    $incident2 = $action($monitor, $dto);
    expect($incident2)->toBeNull()
        ->and($monitor->fresh()->consecutive_failures)->toBe(2)
        ->and(Incident::count())->toBe(0);

    // Attempt 3: consecutive = 3 (Threshold reached!)
    $incident3 = $action($monitor, $dto);
    expect($incident3)->not->toBeNull()
        ->and($incident3->status)->toBe(IncidentStatus::Investigating)
        ->and($monitor->fresh()->consecutive_failures)->toBe(3)
        ->and($monitor->fresh()->status)->toBe(MonitorStatus::Down)
        ->and(Incident::count())->toBe(1);

    $context->clear();
});

test('it prevents duplicate incident creation during worker stampedes via atomic locks', function () {
    $tenant = Tenant::factory()->create();
    $context = app(TenantContext::class);
    $context->set($tenant);

    $monitor = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'consecutive_failure_threshold' => 2,
        'consecutive_failures' => 1, // Next failure will trigger outage
        'status' => MonitorStatus::Healthy,
    ]);

    $action = app(ProcessMonitorFailureAction::class);
    $dto = MonitorCheckResultDTO::failure($monitor->id, '500 Internal Server Error', 500);

    // Worker 1 processes failure and reaches threshold -> opens Incident #1
    $incident1 = $action($monitor, $dto);
    expect($incident1)->not->toBeNull()
        ->and(Incident::count())->toBe(1);

    // Worker 2, Worker 3, Worker 4 simultaneously process failures for the same outage
    $incident2 = $action($monitor, $dto);
    $incident3 = $action($monitor, $dto);

    // Exactly one incident exists; subsequent worker calls return the existing active incident
    expect(Incident::count())->toBe(1)
        ->and($incident2->id)->toBe($incident1->id)
        ->and($incident3->id)->toBe($incident1->id);

    $context->clear();
});

test('it recovers monitor status and resolves open incidents upon successful check', function () {
    $tenant = Tenant::factory()->create();
    $context = app(TenantContext::class);
    $context->set($tenant);

    $monitor = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'consecutive_failure_threshold' => 1,
        'consecutive_failures' => 1,
        'status' => MonitorStatus::Down,
    ]);

    $incident = Incident::factory()->create([
        'tenant_id' => $tenant->id,
        'monitor_id' => $monitor->id,
        'status' => IncidentStatus::Investigating,
        'resolved_at' => null,
    ]);

    $recoveryAction = app(ProcessMonitorRecoveryAction::class);
    $dto = MonitorCheckResultDTO::success($monitor->id, 200, 145);

    $resolved = $recoveryAction($monitor, $dto);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($incident->id)
        ->and($resolved->fresh()->status)->toBe(IncidentStatus::Resolved)
        ->and($resolved->fresh()->resolved_at)->not->toBeNull()
        ->and($monitor->fresh()->status)->toBe(MonitorStatus::Healthy)
        ->and($monitor->fresh()->consecutive_failures)->toBe(0);

    $context->clear();
});
