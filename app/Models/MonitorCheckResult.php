<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $monitor_id
 * @property int|null $status_code
 * @property int|null $response_time_ms
 * @property bool $is_successful
 * @property string|null $error_message
 * @property bool|null $ssl_is_valid
 * @property int|null $ssl_days_remaining
 * @property Carbon $checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'monitor_id',
    'status_code',
    'response_time_ms',
    'is_successful',
    'error_message',
    'ssl_is_valid',
    'ssl_days_remaining',
    'checked_at',
])]
class MonitorCheckResult extends Model
{
    use BelongsToTenant;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'monitor_id' => 'integer',
            'status_code' => 'integer',
            'response_time_ms' => 'integer',
            'is_successful' => 'boolean',
            'ssl_is_valid' => 'boolean',
            'ssl_days_remaining' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    /**
     * Parent monitor.
     *
     * @return BelongsTo<Monitor, $this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
