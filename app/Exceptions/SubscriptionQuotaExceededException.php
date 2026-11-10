<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class SubscriptionQuotaExceededException extends Exception
{
    public static function monitorLimitReached(int $limit): self
    {
        return new self(
            sprintf('Your current subscription plan allows a maximum of %d monitors. Please upgrade to add more.', $limit)
        );
    }
}
