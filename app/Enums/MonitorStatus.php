<?php

declare(strict_types=1);

namespace App\Enums;

enum MonitorStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Down = 'down';
    case Paused = 'paused';

    /**
     * Determine if the monitor is currently considered available.
     */
    public function isAvailable(): bool
    {
        return in_array($this, [self::Healthy, self::Degraded], true);
    }

    /**
     * Human-readable label for UI/status pages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Operational',
            self::Degraded => 'Degraded Performance',
            self::Down => 'Major Outage',
            self::Paused => 'Paused',
        };
    }
}
