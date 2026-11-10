<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'monitor_id' => Monitor::factory(),
            'title' => 'Service Outage: '.fake()->words(3, true),
            'status' => IncidentStatus::Investigating,
            'severity' => IncidentSeverity::Major,
            'description' => fake()->paragraph(),
            'root_cause_analysis' => null,
            'started_at' => now()->subMinutes(15),
            'resolved_at' => null,
        ];
    }

    /**
     * Mark the incident as resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now(),
            'root_cause_analysis' => 'Upstream DNS failure resolved after nameserver propagation.',
        ]);
    }
}
