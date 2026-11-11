<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentChatMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentChatMessage>
 */
class IncidentChatMessageFactory extends Factory
{
    protected $model = IncidentChatMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'incident_id' => Incident::factory(),
            'user_id' => User::factory(),
            'message' => fake()->sentence(),
        ];
    }
}

