<?php

declare(strict_types=1);

namespace App\Enums;

enum IncidentSeverity: string
{
    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';

    /**
     * Determine badge color for frontend presentation.
     */
    public function color(): string
    {
        return match ($this) {
            self::Minor => 'yellow',
            self::Major => 'orange',
            self::Critical => 'red',
        };
    }
}
