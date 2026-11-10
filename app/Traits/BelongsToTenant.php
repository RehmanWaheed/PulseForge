<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Trait BelongsToTenant
 *
 * Enforces automatic tenant scoping and lifecycle protection.
 */
trait BelongsToTenant
{
    /**
     * Boot the trait and apply the global tenant scope and lifecycle safety hooks.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($model->getAttribute('tenant_id') === null) {
                if (! $context->check()) {
                    throw new RuntimeException(
                        sprintf('Cannot create [%s] without an active TenantContext.', static::class)
                    );
                }

                $model->setAttribute('tenant_id', $context->id());
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw new RuntimeException(
                    sprintf('Reassigning tenant_id on [%s] is strictly forbidden.', static::class)
                );
            }
        });
    }

    /**
     * Relationship back to the parent Tenant.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
