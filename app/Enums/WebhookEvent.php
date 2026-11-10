<?php

declare(strict_types=1);

namespace App\Enums;

enum WebhookEvent: string
{
    case IncidentOpened = 'incident.opened';
    case IncidentResolved = 'incident.resolved';
    case MonitorDown = 'monitor.down';
    case MonitorRecovered = 'monitor.recovered';

    /**
     * Get user-friendly label.
     */
    public function label(): string
    {
        return match ($this) {
            self::IncidentOpened => 'Incident Opened',
            self::IncidentResolved => 'Incident Resolved',
            self::MonitorDown => 'Monitor Down',
            self::MonitorRecovered => 'Monitor Recovered',
        };
    }
}
