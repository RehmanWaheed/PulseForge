<?php

declare(strict_types=1);

namespace App\Actions\Monitors;

use App\DTOs\MonitorCheckResultDTO;
use App\Models\Monitor;
use Illuminate\Support\Facades\Http;
use Throwable;

class PerformMonitorCheckAction
{
    /**
     * Probe a target monitor endpoint and measure response metrics.
     */
    public function __invoke(Monitor $monitor): MonitorCheckResultDTO
    {
        $startTime = hrtime(true);

        try {
            $response = Http::timeout($monitor->timeout_seconds)
                ->withHeaders([
                    'User-Agent' => 'PulseForge-HealthChecker/1.0 (+https://pulseforge.test)',
                    'Accept' => '*/*',
                ])
                ->send($monitor->method->value, $monitor->url);

            $durationMs = (int) round((hrtime(true) - $startTime) / 1e6);
            $statusCode = $response->status();

            if ($statusCode === $monitor->expected_status_code) {
                return MonitorCheckResultDTO::success(
                    monitorId: $monitor->id,
                    statusCode: $statusCode,
                    responseTimeMs: $durationMs,
                    sslIsValid: $this->isSslUrl($monitor->url),
                );
            }

            return MonitorCheckResultDTO::failure(
                monitorId: $monitor->id,
                errorMessage: sprintf(
                    'Expected HTTP status %d, but received %d.',
                    $monitor->expected_status_code,
                    $statusCode
                ),
                statusCode: $statusCode,
                responseTimeMs: $durationMs,
                sslIsValid: $this->isSslUrl($monitor->url),
            );
        } catch (Throwable $e) {
            $durationMs = (int) round((hrtime(true) - $startTime) / 1e6);

            return MonitorCheckResultDTO::failure(
                monitorId: $monitor->id,
                errorMessage: $e->getMessage(),
                statusCode: null,
                responseTimeMs: $durationMs,
                sslIsValid: false,
            );
        }
    }

    /**
     * Check if the target URL utilizes HTTPS.
     */
    protected function isSslUrl(string $url): bool
    {
        return str_starts_with(strtolower($url), 'https://');
    }
}
