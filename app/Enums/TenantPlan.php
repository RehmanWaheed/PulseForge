<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantPlan: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Enterprise = 'enterprise';

    /**
     * Get the default monitor limit allowed for this plan tier.
     */
    public function defaultMonitorLimit(): int
    {
        return match ($this) {
            self::Free => 5,
            self::Pro => 50,
            self::Enterprise => 500,
        };
    }

    /**
     * Get the minimum frequency interval between health checks in seconds.
     */
    public function checkIntervalSeconds(): int
    {
        return match ($this) {
            self::Free => 300, // 5 minutes
            self::Pro => 60,   // 1 minute
            self::Enterprise => 15, // 15 seconds
        };
    }

    /**
     * Determine whether AI-generated incident post-mortems (Gemini) are enabled.
     */
    public function hasAiPostMortem(): bool
    {
        return match ($this) {
            self::Free => false,
            self::Pro, self::Enterprise => true,
        };
    }

    /**
     * Maximum API calls allowed per minute.
     */
    public function rateLimitPerMinute(): int
    {
        return match ($this) {
            self::Free => 60,
            self::Pro => 300,
            self::Enterprise => 1200,
        };
    }
}
