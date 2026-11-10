<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MonitorMethod;
use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    protected $model = Monitor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->domainWord().' API',
            'url' => fake()->url(),
            'method' => MonitorMethod::Get,
            'check_interval_seconds' => 60,
            'timeout_seconds' => 10,
            'expected_status_code' => 200,
            'consecutive_failure_threshold' => 3,
            'consecutive_failures' => 0,
            'status' => MonitorStatus::Healthy,
            'is_active' => true,
            'ssl_check_enabled' => true,
            'ssl_expires_at' => now()->addDays(90),
            'last_checked_at' => now(),
            'last_status_change_at' => now(),
        ];
    }

    /**
     * Set the monitor to down status with failures.
     */
    public function down(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MonitorStatus::Down,
            'consecutive_failures' => 3,
        ]);
    }
}
