<?php

declare(strict_types=1);

use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int|string $id): bool {
    return (int) $user->id === (int) $id;
});

/**
 * Authorize private channel for tenant-scoped events (heartbeats, monitor alerts).
 */
Broadcast::channel('tenant.{tenantId}', function (User $user, int|string $tenantId): bool {
    return $user->tenants()->where('tenants.id', (int) $tenantId)->exists();
});

/**
 * Authorize private channel for incident war-room live chat.
 */
Broadcast::channel('tenant.{tenantId}.incident.{incidentId}', function (User $user, int|string $tenantId, int|string $incidentId): bool {
    $belongsToTenant = $user->tenants()->where('tenants.id', (int) $tenantId)->exists();

    if (! $belongsToTenant) {
        return false;
    }

    return Incident::withoutGlobalScopes()
        ->where('id', (int) $incidentId)
        ->where('tenant_id', (int) $tenantId)
        ->exists();
});
