<?php

declare(strict_types=1);

use App\Events\IncidentCreatedEvent;
use App\Events\IncidentResolvedEvent;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\IncidentOpenedNotification;
use App\Notifications\IncidentResolvedNotification;
use Illuminate\Support\Facades\Notification;

test('it sends IncidentOpenedNotification to tenant users when incident is created', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['role' => 'admin']);

    $monitor = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'API Gateway',
        'url' => 'https://api.example.com/health',
    ]);

    $incident = Incident::factory()->create([
        'tenant_id' => $tenant->id,
        'monitor_id' => $monitor->id,
        'title' => 'Service Outage: API Gateway',
    ]);

    IncidentCreatedEvent::dispatch($incident);

    Notification::assertSentTo(
        $user,
        IncidentOpenedNotification::class,
        function (IncidentOpenedNotification $notification) use ($incident) {
            $mail = $notification->toMail(new stdClass);

            return $notification->incident->id === $incident->id
                && str_contains($mail->subject, '🚨 [CRITICAL] Service Outage: API Gateway');
        }
    );
});

test('it sends IncidentResolvedNotification to tenant users when incident is resolved', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user->id, ['role' => 'admin']);

    $monitor = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Payment Service',
        'url' => 'https://pay.example.com/health',
    ]);

    $incident = Incident::factory()->create([
        'tenant_id' => $tenant->id,
        'monitor_id' => $monitor->id,
        'title' => 'Service Outage: Payment Service',
        'resolved_at' => now(),
    ]);

    IncidentResolvedEvent::dispatch($incident);

    Notification::assertSentTo(
        $user,
        IncidentResolvedNotification::class,
        function (IncidentResolvedNotification $notification) use ($incident) {
            $mail = $notification->toMail(new stdClass);

            return $notification->incident->id === $incident->id
                && str_contains($mail->subject, '✅ [RESOLVED] Service Recovered: Payment Service');
        }
    );
});

test('it does not send notifications if tenant has no users attached', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $monitor = Monitor::factory()->create(['tenant_id' => $tenant->id]);
    $incident = Incident::factory()->create([
        'tenant_id' => $tenant->id,
        'monitor_id' => $monitor->id,
    ]);

    IncidentCreatedEvent::dispatch($incident);

    Notification::assertNothingSent();
});
