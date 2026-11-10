<?php

declare(strict_types=1);

use App\Enums\TenantPlan;

test('it provides correct monitor limits per plan', function () {
    expect(TenantPlan::Free->defaultMonitorLimit())->toBe(5)
        ->and(TenantPlan::Pro->defaultMonitorLimit())->toBe(50)
        ->and(TenantPlan::Enterprise->defaultMonitorLimit())->toBe(500);
});

test('it provides correct check intervals per plan', function () {
    expect(TenantPlan::Free->checkIntervalSeconds())->toBe(300)
        ->and(TenantPlan::Pro->checkIntervalSeconds())->toBe(60)
        ->and(TenantPlan::Enterprise->checkIntervalSeconds())->toBe(15);
});

test('it correctly gates AI post-mortem feature based on tier', function () {
    expect(TenantPlan::Free->hasAiPostMortem())->toBeFalse()
        ->and(TenantPlan::Pro->hasAiPostMortem())->toBeTrue()
        ->and(TenantPlan::Enterprise->hasAiPostMortem())->toBeTrue();
});

test('it enforces rate limits per tier', function () {
    expect(TenantPlan::Free->rateLimitPerMinute())->toBe(60)
        ->and(TenantPlan::Pro->rateLimitPerMinute())->toBe(300)
        ->and(TenantPlan::Enterprise->rateLimitPerMinute())->toBe(1200);
});
