<?php

declare(strict_types=1);

namespace App\Actions\Incidents;

use App\Actions\Webhooks\QueueWebhookForEventAction;
use App\DTOs\MonitorCheckResultDTO;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\MonitorStatus;
use App\Enums\WebhookEvent;
use App\Events\IncidentCreatedEvent;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheckResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProcessMonitorFailureAction
{
    /**
     * Process a failed monitor check with Redis Atomic Locking & DB row locks to prevent worker stampedes.
     */
    public function __invoke(Monitor $monitor, MonitorCheckResultDTO $dto): ?Incident
    {
        $lockKey = sprintf('incident:monitor:%d', $monitor->id);

        /** @var Incident|null $incident */
        $incident = Cache::lock($lockKey, 10)->block(5, function () use ($monitor, $dto): ?Incident {
            return DB::transaction(function () use ($monitor, $dto): ?Incident {
                /** @var Monitor $lockedMonitor */
                $lockedMonitor = Monitor::query()
                    ->where('id', $monitor->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // 1. Record the failed check result
                MonitorCheckResult::create([
                    'tenant_id' => $lockedMonitor->tenant_id,
                    'monitor_id' => $lockedMonitor->id,
                    'status_code' => $dto->statusCode,
                    'response_time_ms' => $dto->responseTimeMs,
                    'is_successful' => false,
                    'error_message' => $dto->errorMessage,
                    'ssl_is_valid' => $dto->sslIsValid,
                    'checked_at' => now(),
                ]);

                // 2. Increment consecutive failure count
                $lockedMonitor->consecutive_failures += 1;
                $lockedMonitor->last_checked_at = now();

                // 3. Evaluate threshold
                if ($lockedMonitor->consecutive_failures < $lockedMonitor->consecutive_failure_threshold) {
                    // Temporary blip; threshold not yet met, keep healthy/degraded without opening an incident
                    $lockedMonitor->save();

                    return null;
                }

                // 4. Check for existing unresolved incident with row lock
                /** @var Incident|null $existingIncident */
                $existingIncident = Incident::query()
                    ->where('monitor_id', $lockedMonitor->id)
                    ->whereNull('resolved_at')
                    ->lockForUpdate()
                    ->first();

                if ($existingIncident !== null) {
                    // Outage already acknowledged and tracked; prevent worker stampede duplication
                    $lockedMonitor->save();

                    return $existingIncident;
                }

                // 5. Open new incident
                $lockedMonitor->status = MonitorStatus::Down;
                $lockedMonitor->last_status_change_at = now();
                $lockedMonitor->save();

                /** @var Incident $newIncident */
                $newIncident = Incident::create([
                    'tenant_id' => $lockedMonitor->tenant_id,
                    'monitor_id' => $lockedMonitor->id,
                    'title' => sprintf('Service Outage: %s', $lockedMonitor->name),
                    'status' => IncidentStatus::Investigating,
                    'severity' => IncidentSeverity::Major,
                    'description' => sprintf(
                        'Monitor [%s] failed %d consecutive checks. Reason: %s',
                        $lockedMonitor->url,
                        $lockedMonitor->consecutive_failures,
                        $dto->errorMessage ?? 'Unknown HTTP error'
                    ),
                    'started_at' => now(),
                ]);

                IncidentCreatedEvent::dispatch($newIncident);

                if ($lockedMonitor->tenant !== null) {
                    app(QueueWebhookForEventAction::class)(
                        $lockedMonitor->tenant,
                        WebhookEvent::IncidentOpened,
                        [
                            'incident_id' => $newIncident->id,
                            'monitor_id' => $lockedMonitor->id,
                            'title' => $newIncident->title,
                            'severity' => $newIncident->severity->value,
                            'timestamp' => now()->toIso8601String(),
                        ]
                    );
                }

                return $newIncident;
            });
        });

        return $incident;
    }
}
