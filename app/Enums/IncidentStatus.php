<?php

declare(strict_types=1);

namespace App\Enums;

enum IncidentStatus: string
{
    case Investigating = 'investigating';
    case Identified = 'identified';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';

    /**
     * Determine if the incident is currently active.
     */
    public function isOpen(): bool
    {
        return $this !== self::Resolved;
    }

    /**
     * Determine if the incident has been resolved.
     */
    public function isResolved(): bool
    {
        return $this === self::Resolved;
    }
}
