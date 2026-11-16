<?php

declare(strict_types=1);

namespace App\Actions\Monitors;

use App\DTOs\CreateMonitorDTO;
use App\Enums\MonitorStatus;
use App\Exceptions\DuplicateMonitorException;
use App\Exceptions\SubscriptionQuotaExceededException;
use App\Models\Monitor;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CreateMonitorAction
{
    /**
     * Create a new monitor while defending against concurrent quota bypasses and duplicate entries via pessimistic locking.
     *
     * @throws SubscriptionQuotaExceededException
     * @throws DuplicateMonitorException
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

            if ($lockedTenant->monitors()->where('url', $dto->url)->exists()) {
                throw DuplicateMonitorException::forUrl($dto->url);
            }

            if ($lockedTenant->monitors()->where('name', $dto->name)->exists()) {
                throw DuplicateMonitorException::forName($dto->name);
            }

            return Monitor::create([
                'tenant_id' => $lockedTenant->id,
                'status' => MonitorStatus::Healthy,
                // The "..." is PHP 8.1+ Array Unpacking (Spread Operator). It unpacks all
                // key-value pairs from the DTO array directly into this create() payload,
                // replacing verbose array_merge() while preserving keys.
                ...$dto->toArray(),
            ]);
        });
    }
}
