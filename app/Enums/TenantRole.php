<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /**
     * Determine if this role can modify subscription and billing details.
     */
    public function canManageBilling(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Determine if this role can invite or remove team members.
     */
    public function canManageTeam(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /**
     * Determine if this role can create, edit, or delete monitors and webhooks.
     */
    public function canMutateMonitors(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }
}
