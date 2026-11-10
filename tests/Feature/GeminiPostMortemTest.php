<?php

declare(strict_types=1);

use App\Actions\Incidents\GenerateIncidentPostMortemAction;
use App\Enums\TenantPlan;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Tenant;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

test('it gates AI post-mortems and blocks execution for Free tier tenants', function () {
    $tenant = Tenant::factory()->create([
        'plan' => TenantPlan::Free,
    ]);

    $monitor = Monitor::factory()->create(['tenant_id' => $tenant->id]);
    $incident = Incident::factory()->resolved()->create([
        'tenant_id' => $tenant->id,
        'monitor_id' => $monitor->id,
        'root_cause_analysis' => null,
    ]);

    $action = app(GenerateIncidentPostMortemAction::class);
    $result = $action($incident);

    expect($result)->toBeNull()
        ->and($incident->fresh()->root_cause_analysis)->toBeNull();
});

test('it calls Gemini API and stores Root Cause Analysis for Pro tier tenants', function () {
    Config::set('services.gemini.api_key', 'mock_test_key_gemini');

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => "### Root Cause Analysis\n\n**Executive Summary:** Upstream DNS resolver failure.\n**Mitigation:** Configured redundant DNS providers.",
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $tenant = Tenant::factory()->pro()->create();
    $monitor = Monitor::factory()->create(['tenant_id' => $tenant->id]);
    $incident = Incident::factory()->resolved()->create([
        'tenant_id' => $tenant->id,
        'monitor_id' => $monitor->id,
        'root_cause_analysis' => null,
    ]);

    $action = app(GenerateIncidentPostMortemAction::class);
    $rca = $action($incident);

    expect($rca)->not->toBeNull()
        ->and($rca)->toContain('Root Cause Analysis')
        ->and($incident->fresh()->root_cause_analysis)->toBe($rca);

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), 'generativelanguage.googleapis.com')
            && str_contains($request->body(), 'Incident Post-Mortem');
    });
});

test('it gracefully handles Gemini API errors without crashing', function () {
    Config::set('services.gemini.api_key', 'mock_test_key_gemini');

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response('Quota Exceeded', 429),
    ]);

    $tenant = Tenant::factory()->enterprise()->create();
    $monitor = Monitor::factory()->create(['tenant_id' => $tenant->id]);
    $incident = Incident::factory()->resolved()->create([
        'tenant_id' => $tenant->id,
        'monitor_id' => $monitor->id,
        'root_cause_analysis' => null,
    ]);

    $action = app(GenerateIncidentPostMortemAction::class);
    $rca = $action($incident);

    expect($rca)->toBeNull()
        ->and($incident->fresh()->root_cause_analysis)->toBeNull();
});
