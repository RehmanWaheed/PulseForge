<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use RuntimeException;

class TenantContext
{
    protected ?Tenant $currentTenant = null;

    /**
     * Set the currently active tenant.
     */
    public function set(?Tenant $tenant): void
    {
        $this->currentTenant = $tenant;
    }

    /**
     * Retrieve the currently active tenant, or null if unauthenticated / global context.
     */
    public function get(): ?Tenant
    {
        return $this->currentTenant;
    }

    /**
     * Retrieve the active tenant or throw an exception if none is resolved.
     *
     * @throws RuntimeException
     */
    public function getOrFail(): Tenant
    {
        if ($this->currentTenant === null) {
            throw new RuntimeException('Tenant context has not been initialized for this operation.');
        }

        return $this->currentTenant;
    }

    /**
     * Retrieve the active tenant ID, or null.
     */
    public function id(): ?int
    {
        return $this->currentTenant?->id;
    }

    /**
     * Check if a tenant context is currently initialized.
     */
    public function check(): bool
    {
        return $this->currentTenant !== null;
    }

    /**
     * Reset the tenant context (crucial for long-running processes, workers, and tests).
     */
    public function clear(): void
    {
        $this->currentTenant = null;
    }
}
