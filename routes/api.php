<?php

use App\Actions\Billing\HandleStripeWebhookAction;
use App\Actions\Monitors\CreateMonitorAction;
use App\DTOs\CreateMonitorDTO;
use App\Models\Incident;
use App\Models\Monitor;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhooks/stripe', function (Request $request, HandleStripeWebhookAction $action) {
    /** @var array<string, mixed> $payload */
    $payload = $request->all();

    $handled = $action($payload);

    return response()->json(['received' => $handled]);
});

Route::middleware('tenant')->prefix('v1')->group(function (): void {
    Route::get('/workspace', function (TenantContext $context) {
        $tenant = $context->getOrFail();

        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'plan' => $tenant->plan->value,
            'monitor_limit' => $tenant->monitor_limit,
        ]);
    });

    Route::get('/monitors', function () {
        return response()->json(Monitor::all());
    });

    Route::post('/monitors', function (Request $request, TenantContext $context, CreateMonitorAction $action) {
        $tenant = $context->getOrFail();
        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('monitors', 'name')->where('tenant_id', $tenant->id),
            ],
            'url' => [
                'required',
                'url',
                Rule::unique('monitors', 'url')->where('tenant_id', $tenant->id),
            ],
            'check_interval_seconds' => ['nullable', 'integer', 'min:10'],
            'expected_status_code' => ['nullable', 'integer'],
        ], [
            'name.unique' => 'A monitor with this name already exists in your workspace.',
            'url.unique' => 'A monitor for this target URL already exists in your workspace.',
        ]);

        $dto = CreateMonitorDTO::fromArray($validated);
        $monitor = $action($tenant, $dto);

        return response()->json($monitor, 201);
    });

    Route::get('/incidents', function () {
        return response()->json(Incident::latest()->get());
    });
});
