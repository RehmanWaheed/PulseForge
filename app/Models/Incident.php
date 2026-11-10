<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Traits\BelongsToTenant;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $monitor_id
 * @property string $title
 * @property IncidentStatus $status
 * @property IncidentSeverity $severity
 * @property string|null $description
 * @property string|null $root_cause_analysis
 * @property Carbon $started_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'monitor_id',
    'title',
    'status',
    'severity',
    'description',
    'root_cause_analysis',
    'started_at',
    'resolved_at',
])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
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
            'monitor_id' => 'integer',
            'status' => IncidentStatus::class,
            'severity' => IncidentSeverity::class,
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Associated monitor.
     *
     * @return BelongsTo<Monitor, $this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    /**
     * Resolve the active incident.
     */
    public function resolve(?string $rootCause = null): void
    {
        $this->update([
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now(),
            'root_cause_analysis' => $rootCause ?? $this->root_cause_analysis,
        ]);
    }

    /**
     * Outage duration in whole minutes.
     */
    public function durationMinutes(): ?int
    {
        if ($this->resolved_at === null) {
            return (int) $this->started_at->diffInMinutes(now());
        }

        return (int) $this->started_at->diffInMinutes($this->resolved_at);
    }
}
