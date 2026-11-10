<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\TenantPlan;
use App\Models\Tenant;

class HandleStripeWebhookAction
{
    /**
     * Handle incoming Stripe webhook events and sync subscription states.
     *
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(array $payload): bool
    {
        $type = (string) ($payload['type'] ?? '');

        /** @var array<string, mixed> $dataObject */
        $dataObject = $payload['data']['object'] ?? [];

        return match ($type) {
            'customer.subscription.created', 'customer.subscription.updated' => $this->handleSubscriptionUpdated($dataObject),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($dataObject),
            default => true,
        };
    }

    /**
     * Synchronize created or upgraded subscriptions.
     *
     * @param  array<string, mixed>  $subscription
     */
    protected function handleSubscriptionUpdated(array $subscription): bool
    {
        $customerId = (string) ($subscription['customer'] ?? '');
        $subscriptionId = (string) ($subscription['id'] ?? '');
        $status = (string) ($subscription['status'] ?? '');

        /** @var array<string, mixed> $metadata */
        $metadata = $subscription['metadata'] ?? [];
        $tenantId = isset($metadata['tenant_id']) ? (int) $metadata['tenant_id'] : null;

        $tenant = $this->findTenant($customerId, $subscriptionId, $tenantId);

        if ($tenant === null) {
            return false;
        }

        if ($status === 'active' || $status === 'trialing') {
            $planName = (string) ($metadata['plan'] ?? 'pro');
            $plan = TenantPlan::tryFrom(strtolower($planName)) ?? TenantPlan::Pro;

            $tenant->update([
                'plan' => $plan,
                'monitor_limit' => $plan->defaultMonitorLimit(),
                'stripe_customer_id' => $customerId !== '' ? $customerId : $tenant->stripe_customer_id,
                'stripe_subscription_id' => $subscriptionId,
            ]);
        }

        return true;
    }

    /**
     * Handle canceled subscriptions and downgrade to free tier.
     *
     * @param  array<string, mixed>  $subscription
     */
    protected function handleSubscriptionDeleted(array $subscription): bool
    {
        $customerId = (string) ($subscription['customer'] ?? '');
        $subscriptionId = (string) ($subscription['id'] ?? '');

        /** @var array<string, mixed> $metadata */
        $metadata = $subscription['metadata'] ?? [];
        $tenantId = isset($metadata['tenant_id']) ? (int) $metadata['tenant_id'] : null;

        $tenant = $this->findTenant($customerId, $subscriptionId, $tenantId);

        if ($tenant === null) {
            return false;
        }

        $tenant->update([
            'plan' => TenantPlan::Free,
            'monitor_limit' => TenantPlan::Free->defaultMonitorLimit(),
            'stripe_subscription_id' => null,
        ]);

        return true;
    }

    /**
     * Locate tenant by customer ID, subscription ID, or metadata tenant ID.
     */
    protected function findTenant(string $customerId, string $subscriptionId, ?int $tenantId): ?Tenant
    {
        if ($tenantId !== null) {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant !== null) {
                return $tenant;
            }
        }

        if ($customerId !== '') {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::query()->where('stripe_customer_id', $customerId)->first();
            if ($tenant !== null) {
                return $tenant;
            }
        }

        if ($subscriptionId !== '') {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::query()->where('stripe_subscription_id', $subscriptionId)->first();
            if ($tenant !== null) {
                return $tenant;
            }
        }

        return null;
    }
}
