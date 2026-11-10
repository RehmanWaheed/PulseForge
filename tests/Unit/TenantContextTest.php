<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;

test('tenant context initializes empty and returns null', function () {
    $context = new TenantContext;

    expect($context->get())->toBeNull()
        ->and($context->id())->toBeNull()
        ->and($context->check())->toBeFalse();
});

test('tenant context stores and clears the active tenant', function () {
    $context = new TenantContext;
    $tenant = new Tenant;
    $tenant->id = 42;

    $context->set($tenant);

    expect($context->check())->toBeTrue()
        ->and($context->get())->toBe($tenant)
        ->and($context->id())->toBe(42)
        ->and($context->getOrFail())->toBe($tenant);

    $context->clear();

    expect($context->check())->toBeFalse()
        ->and($context->get())->toBeNull()
        ->and($context->id())->toBeNull();
});

test('getOrFail throws RuntimeException when tenant context is unset', function () {
    $context = new TenantContext;

    expect(fn () => $context->getOrFail())
        ->toThrow(RuntimeException::class, 'Tenant context has not been initialized for this operation.');
});
