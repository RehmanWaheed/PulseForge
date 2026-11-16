<?php

declare(strict_types=1);

use App\Actions\Incidents\ProcessMonitorFailureAction;
use App\Actions\Incidents\ProcessMonitorRecoveryAction;
use App\Actions\Incidents\SendIncidentChatMessageAction;
use App\Actions\Monitors\PerformMonitorCheckAction;
use App\Events\MonitorCheckedEvent;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheckResult;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Guest Authentication Routes
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Protected Workspace Routes
Route::middleware('auth')->group(function (): void {
    Route::get('/', function () {
        return redirect('/demo/realtime');
    });

    Route::get('/demo/realtime', function (Request $request, TenantContext $context) {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        // Determine active workspace:
        // Super admin can inspect any workspace via ?tenant=slug, default to Acme
        // Standard users are scoped to their first assigned workspace
        if ($currentUser->is_platform_admin) {
            $slug = (string) $request->query('tenant', 'acme');
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->where('slug', $slug)->first()
                ?? Tenant::query()->firstOrFail();
        } else {
            /** @var Tenant $tenant */
            $tenant = $currentUser->tenants()->first()
                ?? Tenant::query()->where('slug', 'acme')->firstOrFail();
        }

        $context->set($tenant);

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

        $checkLogs = MonitorCheckResult::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with('monitor')
            ->latest('checked_at')
            ->take(15)
            ->get();

        return view('demo.realtime', [
            'tenant' => $tenant,
            'currentUser' => $currentUser,
            'monitors' => $monitors,
            'incident' => $incident,
            'messages' => $messages,
            'checkLogs' => $checkLogs,
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

        /** @var User $user */
        $user = Auth::user();

        /** @var Incident $incident */
        $incident = Incident::withoutGlobalScopes()->findOrFail($validated['incident_id']);

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->findOrFail($incident->tenant_id);
        $context->set($tenant);

        if (! $user->is_platform_admin && ! $user->belongsToTenant($tenant)) {
            return response()->json(['error' => 'Unauthorized tenant access.'], 403);
        }

        $chatMessage = $action($incident, $user, (string) $validated['message']);

        return response()->json([
            'success' => true,
            'message' => $chatMessage,
        ], 201);
    });

    Route::post('/demo/realtime/trigger-check', function (
        Request $request,
        TenantContext $context,
        PerformMonitorCheckAction $action,
        ProcessMonitorRecoveryAction $recoveryAction,
        ProcessMonitorFailureAction $failureAction
    ) {
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

        if ($resultDTO->isSuccessful) {
            $recoveryAction($monitor, $resultDTO);
        } else {
            $failureAction($monitor, $resultDTO);
        }

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
});
