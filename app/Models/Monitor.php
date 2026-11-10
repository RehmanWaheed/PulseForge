<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MonitorMethod;
use App\Enums\MonitorStatus;
use App\Traits\BelongsToTenant;
use Database\Factories\MonitorFactory;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $url
 * @property MonitorMethod $method
 * @property int $check_interval_seconds
 * @property int $timeout_seconds
 * @property int $expected_status_code
 * @property int $consecutive_failure_threshold
 * @property int $consecutive_failures
 * @property MonitorStatus $status
 * @property bool $is_active
 * @property bool $ssl_check_enabled
 * @property Carbon|null $ssl_expires_at
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $last_status_change_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> withoutTenancy()
 * @method static Builder<static> dueForCheck()
 */
#[Fillable([
    'tenant_id',
    'name',
    'url',
    'method',
    'check_interval_seconds',
    'timeout_seconds',
    'expected_status_code',
    'consecutive_failure_threshold',
    'consecutive_failures',
    'status',
    'is_active',
    'ssl_check_enabled',
    'ssl_expires_at',
    'last_checked_at',
    'last_status_change_at',
])]
class Monitor extends Model
{
    /** @use HasFactory<MonitorFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'method' => MonitorMethod::class,
            'status' => MonitorStatus::class,
            'check_interval_seconds' => 'integer',
            'timeout_seconds' => 'integer',
            'expected_status_code' => 'integer',
            'consecutive_failure_threshold' => 'integer',
            'consecutive_failures' => 'integer',
            'is_active' => 'boolean',
            'ssl_check_enabled' => 'boolean',
            'ssl_expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_status_change_at' => 'datetime',
        ];
    }

    /**
     * Historical check results recorded for this monitor.
     *
     * @return HasMany<MonitorCheckResult, $this>
     */
    public function checkResults(): HasMany
    {
        return $this->hasMany(MonitorCheckResult::class);
    }

    /**
     * Outages/Incidents associated with this monitor.
     *
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * Current active open incident, if one exists.
     *
     * @return HasOne<Incident, $this>
     */
    public function openIncident(): HasOne
    {
        return $this->hasOne(Incident::class)->whereNull('resolved_at');
    }

    /**
     * Determine if monitor is operational.
     */
    public function isHealthy(): bool
    {
        return $this->status === MonitorStatus::Healthy;
    }

    /**
     * Determine if monitor is down.
     */
    public function isDown(): bool
    {
        return $this->status === MonitorStatus::Down;
    }

    /**
     * Determine if current consecutive failures have met or exceeded threshold.
     */
    public function hasFailedThreshold(): bool
    {
        return $this->consecutive_failures >= $this->consecutive_failure_threshold;
    }

    /**
     * Scope query to monitors eligible for health checking.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeDueForCheck(Builder $query): void
    {
        $connection = $query->getConnection();
        $driver = $connection instanceof Connection ? $connection->getDriverName() : 'mysql';

        $query->where('is_active', true)
            ->where(function (Builder $subQuery) use ($driver): void {
                $subQuery->whereNull('last_checked_at');

                if ($driver === 'sqlite') {
                    $subQuery->orWhereRaw("datetime(last_checked_at, '+' || check_interval_seconds || ' seconds') <= datetime('now')");
                } else {
                    $subQuery->orWhereRaw('TIMESTAMPDIFF(SECOND, last_checked_at, NOW()) >= check_interval_seconds');
                }
            });
    }
}
