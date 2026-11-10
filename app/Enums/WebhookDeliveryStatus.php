<?php

declare(strict_types=1);

namespace App\Enums;

enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case InFlight = 'in_flight';
    case Delivered = 'delivered';
    case Failed = 'failed';

    /**
     * Determine if delivery is completed (either successfully or finally exhausted).
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Failed], true);
    }
}
