<?php

declare(strict_types=1);

use App\Actions\Incidents\SendIncidentChatMessageAction;
use App\Actions\Monitors\PerformMonitorCheckAction;
use App\Events\MonitorCheckedEvent;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/demo/realtime');
});

Route::get('/demo/realtime', function (Request $request, TenantContext $context) {
    /** @var Tenant $tenant */
    $tenant = Tenant::query()->where('slug', 'acme')->first() ?? Tenant::query()->firstOrFail();
    $context->set($tenant);

    $asDevOps = $request->query('as') === 'engineer';
    $email = $asDevOps ? 'engineer@pulseforge.test' : 'admin@pulseforge.test';

    /** @var User $currentUser */
    $currentUser = User::query()->where('email', $email)->first()
        ?? User::query()->firstOrFail();

    Auth::login($currentUser);

    $monitors = Monitor::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->get();

    $incident = Incident::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->latest('started_at')
        ->first();

    $messages = $incident !== null
        ? $incident->chatMessages()->with('user')->oldest()->get()
        : collect();

    return view('demo.realtime', [
        'tenant' => $tenant,
        'currentUser' => $currentUser,
        'monitors' => $monitors,
        'incident' => $incident,
        'messages' => $messages,
        'reverbKey' => (string) config('broadcasting.client.key', ''),
        'reverbHost' => (string) config('broadcasting.client.host', 'localhost'),
        'reverbPort' => (int) config('broadcasting.client.port', 8085),
    ]);
});

Route::post('/demo/realtime/messages', function (Request $request, TenantContext $context, SendIncidentChatMessageAction $action) {
    /** @var array<string, mixed> $validated */
    $validated = $request->validate([
        'incident_id' => ['required', 'integer'],
        'message' => ['required', 'string', 'max:2000'],
    ]);

    /** @var User|null $user */
    $user = $request->user() ?? User::query()->where('email', 'admin@pulseforge.test')->first();

    if ($user === null) {
        return response()->json(['error' => 'Unauthenticated.'], 401);
    }

    /** @var Incident $incident */
    $incident = Incident::withoutGlobalScopes()->findOrFail($validated['incident_id']);

    /** @var Tenant $tenant */
    $tenant = Tenant::query()->findOrFail($incident->tenant_id);
    $context->set($tenant);

    if (! $user->belongsToTenant($tenant)) {
        return response()->json(['error' => 'Unauthorized tenant access.'], 403);
    }

    $chatMessage = $action($incident, $user, (string) $validated['message']);

    return response()->json([
        'success' => true,
        'message' => $chatMessage,
    ], 201);
});

Route::post('/demo/realtime/trigger-check', function (Request $request, TenantContext $context, PerformMonitorCheckAction $action) {
    /** @var array<string, mixed> $validated */
    $validated = $request->validate([
        'monitor_id' => ['required', 'integer'],
    ]);

    /** @var Monitor $monitor */
    $monitor = Monitor::withoutGlobalScopes()->findOrFail($validated['monitor_id']);

    /** @var Tenant $tenant */
    $tenant = Tenant::query()->findOrFail($monitor->tenant_id);
    $context->set($tenant);

    $resultDTO = $action($monitor);

    MonitorCheckedEvent::dispatch($monitor, $resultDTO);

    return response()->json([
        'success' => true,
        'result' => [
            'monitor_id' => $monitor->id,
            'is_successful' => $resultDTO->isSuccessful,
            'response_time_ms' => $resultDTO->responseTimeMs,
            'status_code' => $resultDTO->statusCode,
        ],
    ]);
});
