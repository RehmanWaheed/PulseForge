<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantPlan;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $domain
 * @property TenantPlan $plan
 * @property int $monitor_limit
 * @property bool $is_active
 * @property string|null $stripe_customer_id
 * @property string|null $stripe_subscription_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'slug',
    'domain',
    'plan',
    'monitor_limit',
    'is_active',
    'stripe_customer_id',
    'stripe_subscription_id',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => TenantPlan::class,
            'monitor_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Team members associated with this tenant workspace.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Monitors registered by this tenant.
     *
     * @return HasMany<Monitor, $this>
     */
    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }

    /**
     * Incidents belonging to this tenant.
     *
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * Check if the tenant has reached its allowed monitor quota.
     */
    public function hasReachedMonitorLimit(int $currentCount): bool
    {
        return $currentCount >= $this->monitor_limit;
    }

    /**
     * Check if the workspace is in good standing.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if tenant is on a paid Stripe subscription.
     */
    public function isSubscribed(): bool
    {
        return $this->stripe_subscription_id !== null && $this->plan !== TenantPlan::Free;
    }
}
