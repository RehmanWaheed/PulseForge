<?php

declare(strict_types=1);

namespace App\Actions\Monitors;

use App\DTOs\CreateMonitorDTO;
use App\Enums\MonitorStatus;
use App\Exceptions\SubscriptionQuotaExceededException;
use App\Models\Monitor;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CreateMonitorAction
{
    /**
     * Create a new monitor while defending against concurrent quota bypasses via pessimistic locking.
     *
     * @throws SubscriptionQuotaExceededException
     */
    public function __invoke(Tenant $tenant, CreateMonitorDTO $dto): Monitor
    {
        return DB::transaction(function () use ($tenant, $dto): Monitor {
            /** @var Tenant $lockedTenant */
            $lockedTenant = Tenant::query()
                ->where('id', $tenant->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentCount = $lockedTenant->monitors()->count();

            if ($lockedTenant->hasReachedMonitorLimit($currentCount)) {
                throw SubscriptionQuotaExceededException::monitorLimitReached($lockedTenant->monitor_limit);
            }

            return Monitor::create([
                'tenant_id' => $lockedTenant->id,
                'status' => MonitorStatus::Healthy,
                ...$dto->toArray(),
            ]);
        });
    }
}
