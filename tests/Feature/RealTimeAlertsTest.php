<?php

declare(strict_types=1);

use App\Actions\Incidents\ProcessMonitorFailureAction;
use App\Actions\Incidents\ProcessMonitorRecoveryAction;
use App\DTOs\MonitorCheckResultDTO;
use App\Events\IncidentCreatedEvent;
use App\Events\IncidentResolvedEvent;
use App\Events\MonitorCheckedEvent;
use App\Jobs\CheckMonitorJob;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

test('CheckMonitorJob broadcasts MonitorCheckedEvent on tenant private channel', function () {
    Event::fake([MonitorCheckedEvent::class]);

    Http::fake([
        'https://app.test/ping' => Http::response('OK', 200),
    ]);

    $tenant = Tenant::factory()->create();
    $monitor = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://app.test/ping',
    ]);

    CheckMonitorJob::dispatchSync($monitor);

    Event::assertDispatched(MonitorCheckedEvent::class, function (MonitorCheckedEvent $event) use ($monitor, $tenant) {
        $channels = $event->broadcastOn();

        return $event->monitor->id === $monitor->id
            && $event->result->isSuccessful
            && count($channels) === 1
            && $channels[0]->name === sprintf('private-tenant.%d', $tenant->id);
    });
});

test('ProcessMonitorFailureAction broadcasts IncidentCreatedEvent when threshold breached', function () {
    Event::fake([IncidentCreatedEvent::class]);

    $tenant = Tenant::factory()->create();
    $monitor = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'consecutive_failure_threshold' => 1,
        'consecutive_failures' => 0,
    ]);

    $action = app(ProcessMonitorFailureAction::class);
    $dto = MonitorCheckResultDTO::failure($monitor->id, 'Bad Gateway', 502);

    $incident = $action($monitor, $dto);

    expect($incident)->not->toBeNull();

    Event::assertDispatched(IncidentCreatedEvent::class, function (IncidentCreatedEvent $event) use ($tenant, $incident) {
        $channels = $event->broadcastOn();

        return $event->incident->id === $incident->id
            && count($channels) === 1
            && $channels[0]->name === sprintf('private-tenant.%d', $tenant->id);
    });
});

test('ProcessMonitorRecoveryAction broadcasts IncidentResolvedEvent on service recovery', function () {
    Event::fake([IncidentResolvedEvent::class]);

    $tenant = Tenant::factory()->create();
    $monitor = Monitor::factory()->create(['tenant_id' => $tenant->id]);
    $incident = Incident::factory()->create([
        'tenant_id' => $tenant->id,
        'monitor_id' => $monitor->id,
        'resolved_at' => null,
    ]);

    $action = app(ProcessMonitorRecoveryAction::class);
    $dto = MonitorCheckResultDTO::success($monitor->id, 200, 45);

    $resolved = $action($monitor, $dto);

    expect($resolved)->not->toBeNull();

    Event::assertDispatched(IncidentResolvedEvent::class, function (IncidentResolvedEvent $event) use ($tenant, $incident) {
        $channels = $event->broadcastOn();

        return $event->incident->id === $incident->id
            && count($channels) === 1
            && $channels[0]->name === sprintf('private-tenant.%d', $tenant->id);
    });
});
