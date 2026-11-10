<?php

declare(strict_types=1);

namespace App\Actions\Incidents;

use App\Models\Incident;
use App\Models\MonitorCheckResult;
use App\Services\AI\GeminiClient;
use Illuminate\Database\Eloquent\Collection;

class GenerateIncidentPostMortemAction
{
    public function __construct(
        protected GeminiClient $gemini
    ) {}

    /**
     * Generate an AI-powered Root Cause Analysis and Post-Mortem for resolved incidents.
     */
    public function __invoke(Incident $incident): ?string
    {
        $tenant = $incident->tenant;
        $monitor = $incident->monitor;

        // Plan gating: Free tier workspaces cannot generate AI post-mortems
        if ($tenant === null || ! $tenant->plan->hasAiPostMortem() || $monitor === null) {
            return null;
        }

        $durationMinutes = $incident->durationMinutes() ?? 0;

        /** @var Collection<int, MonitorCheckResult> $recentFailures */
        $recentFailures = $monitor->checkResults()
            ->where('is_successful', false)
            ->latest('checked_at')
            ->take(3)
            ->get();

        $errorSummaries = $recentFailures->map(function (MonitorCheckResult $check): string {
            return sprintf(
                'Status %s, Latency: %sms, Error: %s',
                $check->status_code !== null ? (string) $check->status_code : 'N/A',
                $check->response_time_ms !== null ? (string) $check->response_time_ms : 'N/A',
                $check->error_message ?? 'None'
            );
        })->implode('; ');

        $prompt = <<<PROMPT
You are a Principal Site Reliability Engineer (SRE) generating an Incident Post-Mortem and Root Cause Analysis for a service outage.

Target Endpoint: {$monitor->name} ({$monitor->url})
HTTP Method: {$monitor->method->value}
Expected Status: {$monitor->expected_status_code}
Outage Duration: {$durationMinutes} minute(s)
Incident Title: {$incident->title}
Observed Failures: {$errorSummaries}

Provide a structured, concise Markdown report with:
- **Executive Summary** (1-2 sentences on outage scope)
- **Probable Root Cause** (inferred from telemetry)
- **Impact & Latency Analysis**
- **Actionable Preventative Measures** (3 bullet points)
PROMPT;

        $postMortem = $this->gemini->generateContent($prompt);

        if ($postMortem !== null && $postMortem !== '') {
            $incident->update(['root_cause_analysis' => $postMortem]);

            return $postMortem;
        }

        return null;
    }
}
