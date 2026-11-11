<?php

declare(strict_types=1);

use App\Actions\Incidents\SendIncidentChatMessageAction;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\TenantRole;
use App\Events\IncidentChatMessageSentEvent;
use App\Events\MonitorCheckedEvent;
use App\Models\Incident;
use App\Models\IncidentChatMessage;
use App\Models\Monitor;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

test('SendIncidentChatMessageAction persists message and dispatches broadcast event', function () {
    Event::fake([IncidentChatMessageSentEvent::class]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $incident = Incident::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => IncidentStatus::Investigating,
        'severity' => IncidentSeverity::Major,
    ]);

    $action = app(SendIncidentChatMessageAction::class);
    $chatMessage = $action($incident, $user, 'Investigating connection pool spike');

    expect($chatMessage)->toBeInstanceOf(IncidentChatMessage::class)
        ->and($chatMessage->message)->toBe('Investigating connection pool spike')
        ->and($chatMessage->tenant_id)->toBe($tenant->id)
        ->and($chatMessage->incident_id)->toBe($incident->id)
        ->and($chatMessage->user_id)->toBe($user->id);

    $this->assertDatabaseHas('incident_chat_messages', [
        'id' => $chatMessage->id,
        'message' => 'Investigating connection pool spike',
        'tenant_id' => $tenant->id,
        'incident_id' => $incident->id,
        'user_id' => $user->id,
    ]);

    Event::assertDispatched(IncidentChatMessageSentEvent::class, function (IncidentChatMessageSentEvent $event) use ($chatMessage, $tenant, $incident) {
        $channels = $event->broadcastOn();

        return $event->chatMessage->id === $chatMessage->id
            && $event->broadcastAs() === 'IncidentChatMessageSent'
            && count($channels) === 1
            && $channels[0]->name === sprintf('private-tenant.%d.incident.%d', $tenant->id, $incident->id)
            && $event->broadcastWith()['message'] === 'Investigating connection pool spike';
    });
});

test('incident war room channel authorizes tenant members and rejects non-members', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'pulseforge-key',
        'broadcasting.connections.reverb.secret' => 'pulseforge-secret',
        'broadcasting.connections.reverb.app_id' => 'pulseforge-app',
    ]);
    Broadcast::purge();
    require base_path('routes/channels.php');

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create();
    $userA->tenants()->attach($tenantA->id, ['role' => TenantRole::Admin->value]);

    $userB = User::factory()->create();
    $userB->tenants()->attach($tenantB->id, ['role' => TenantRole::Admin->value]);

    $incidentA = Incident::factory()->create(['tenant_id' => $tenantA->id]);

    // User A can access War Room of Tenant A
    $response = $this->actingAs($userA)
        ->postJson('/broadcasting/auth', [
            'channel_name' => sprintf('private-tenant.%d.incident.%d', $tenantA->id, $incidentA->id),
            'socket_id' => '1234.5678',
        ]);
    $response->assertOk()
        ->assertJsonStructure(['auth']);

    // User B cannot access War Room of Tenant A
    $response = $this->actingAs($userB)
        ->postJson('/broadcasting/auth', [
            'channel_name' => sprintf('private-tenant.%d.incident.%d', $tenantA->id, $incidentA->id),
            'socket_id' => '1234.5678',
        ]);
    $response->assertForbidden();
});

test('tenant private channel authorizes members and rejects non-members', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'pulseforge-key',
        'broadcasting.connections.reverb.secret' => 'pulseforge-secret',
        'broadcasting.connections.reverb.app_id' => 'pulseforge-app',
    ]);
    Broadcast::purge();
    require base_path('routes/channels.php');

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create();
    $userA->tenants()->attach($tenantA->id, ['role' => TenantRole::Member->value]);

    $userB = User::factory()->create();
    $userB->tenants()->attach($tenantB->id, ['role' => TenantRole::Member->value]);

    $response = $this->actingAs($userA)
        ->postJson('/broadcasting/auth', [
            'channel_name' => sprintf('private-tenant.%d', $tenantA->id),
            'socket_id' => '1234.5678',
        ]);
    $response->assertOk()
        ->assertJsonStructure(['auth']);

    $response = $this->actingAs($userB)
        ->postJson('/broadcasting/auth', [
            'channel_name' => sprintf('private-tenant.%d', $tenantA->id),
            'socket_id' => '1234.5678',
        ]);
    $response->assertForbidden();
});

test('POST /demo/realtime/messages stores message and returns JSON', function () {
    Event::fake([IncidentChatMessageSentEvent::class]);

    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $incident = Incident::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)
        ->postJson('/demo/realtime/messages', [
            'incident_id' => $incident->id,
            'message' => 'Redis instance restarted successfully.',
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => [
                'message' => 'Redis instance restarted successfully.',
                'incident_id' => $incident->id,
                'user_id' => $user->id,
            ],
        ]);

    Event::assertDispatched(IncidentChatMessageSentEvent::class);
});

test('POST /demo/realtime/messages rejects user not belonging to tenant', function () {
    $tenant = Tenant::factory()->create();
    $incident = Incident::factory()->create(['tenant_id' => $tenant->id]);

    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)
        ->postJson('/demo/realtime/messages', [
            'incident_id' => $incident->id,
            'message' => 'Sneaky message',
        ]);

    $response->assertForbidden();
});

test('POST /demo/realtime/trigger-check executes monitor check and broadcasts', function () {
    Event::fake([MonitorCheckedEvent::class]);

    Http::fake([
        'https://status.example.com' => Http::response('OK', 200),
    ]);

    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $monitor = Monitor::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://status.example.com',
        'expected_status_code' => 200,
    ]);

    $response = $this->postJson('/demo/realtime/trigger-check', [
        'monitor_id' => $monitor->id,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'result' => [
                'monitor_id' => $monitor->id,
                'is_successful' => true,
                'status_code' => 200,
            ],
        ]);

    Event::assertDispatched(MonitorCheckedEvent::class, function (MonitorCheckedEvent $event) use ($monitor) {
        return $event->monitor->id === $monitor->id
            && $event->result->isSuccessful
            && $event->broadcastAs() === 'MonitorChecked';
    });
});

test('GET /demo/realtime renders successfully for admin and engineer views', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $admin = User::factory()->create(['email' => 'admin@pulseforge.test']);
    $admin->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $engineer = User::factory()->create(['email' => 'engineer@pulseforge.test']);
    $engineer->tenants()->attach($tenant->id, ['role' => TenantRole::Admin->value]);

    $response = $this->get('/demo/realtime');
    $response->assertOk()
        ->assertSee('PulseForge')
        ->assertSee('Reverb Live')
        ->assertSee($admin->name);

    $responseEngineer = $this->get('/demo/realtime?as=engineer');
    $responseEngineer->assertOk()
        ->assertSee('PulseForge')
        ->assertSee($engineer->name);
});
