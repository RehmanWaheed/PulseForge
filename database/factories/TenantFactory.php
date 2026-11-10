<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantPlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'domain' => null,
            'plan' => TenantPlan::Free,
            'monitor_limit' => TenantPlan::Free->defaultMonitorLimit(),
            'is_active' => true,
            'stripe_customer_id' => null,
            'stripe_subscription_id' => null,
        ];
    }

    /**
     * Set the tenant to the Pro plan.
     */
    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => TenantPlan::Pro,
            'monitor_limit' => TenantPlan::Pro->defaultMonitorLimit(),
        ]);
    }

    /**
     * Set the tenant to the Enterprise plan.
     */
    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => TenantPlan::Enterprise,
            'monitor_limit' => TenantPlan::Enterprise->defaultMonitorLimit(),
        ]);
    }

    /**
     * Indicate that the tenant is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
