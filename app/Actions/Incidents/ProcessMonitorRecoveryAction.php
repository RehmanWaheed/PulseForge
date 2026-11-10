<?php

declare(strict_types=1);

namespace App\Actions\Incidents;

use App\Actions\Webhooks\QueueWebhookForEventAction;
use App\DTOs\MonitorCheckResultDTO;
use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Enums\WebhookEvent;
use App\Events\IncidentResolvedEvent;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheckResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProcessMonitorRecoveryAction
{
    /**
     * Process a successful check and resolve open incidents if the service has recovered.
     */
    public function __invoke(Monitor $monitor, MonitorCheckResultDTO $dto): ?Incident
    {
        $lockKey = sprintf('incident:monitor:%d', $monitor->id);

        /** @var Incident|null $resolvedIncident */
        $resolvedIncident = Cache::lock($lockKey, 10)->block(5, function () use ($monitor, $dto): ?Incident {
            return DB::transaction(function () use ($monitor, $dto): ?Incident {
                /** @var Monitor $lockedMonitor */
                $lockedMonitor = Monitor::query()
                    ->where('id', $monitor->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // 1. Record the successful check result
                MonitorCheckResult::create([
                    'tenant_id' => $lockedMonitor->tenant_id,
                    'monitor_id' => $lockedMonitor->id,
                    'status_code' => $dto->statusCode,
                    'response_time_ms' => $dto->responseTimeMs,
                    'is_successful' => true,
                    'error_message' => null,
                    'ssl_is_valid' => $dto->sslIsValid,
                    'ssl_days_remaining' => $dto->sslDaysRemaining,
                    'checked_at' => now(),
                ]);

                // 2. Reset consecutive failure counters
                $lockedMonitor->consecutive_failures = 0;
                $lockedMonitor->last_checked_at = now();

                // 3. Find active incident if one was open
                /** @var Incident|null $openIncident */
                $openIncident = Incident::query()
                    ->where('monitor_id', $lockedMonitor->id)
                    ->whereNull('resolved_at')
                    ->lockForUpdate()
                    ->first();

                if ($openIncident !== null) {
                    $openIncident->update([
                        'status' => IncidentStatus::Resolved,
                        'resolved_at' => now(),
                    ]);

                    $lockedMonitor->status = MonitorStatus::Healthy;
                    $lockedMonitor->last_status_change_at = now();

                    IncidentResolvedEvent::dispatch($openIncident);

                    // Trigger AI Post-Mortem RCA generation (gated by tenant plan)
                    app(GenerateIncidentPostMortemAction::class)($openIncident);

                    if ($lockedMonitor->tenant !== null) {
                        app(QueueWebhookForEventAction::class)(
                            $lockedMonitor->tenant,
                            WebhookEvent::IncidentResolved,
                            [
                                'incident_id' => $openIncident->id,
                                'monitor_id' => $lockedMonitor->id,
                                'resolved_at' => now()->toIso8601String(),
                                'duration_minutes' => $openIncident->durationMinutes(),
                            ]
                        );
                    }
                } elseif ($lockedMonitor->status !== MonitorStatus::Healthy) {
                    $lockedMonitor->status = MonitorStatus::Healthy;
                    $lockedMonitor->last_status_change_at = now();
                }

                $lockedMonitor->save();

                return $openIncident;
            });
        });

        return $resolvedIncident;
    }
}
