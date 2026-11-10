<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'event' => WebhookEvent::IncidentOpened->value,
            'payload' => [
                'event' => WebhookEvent::IncidentOpened->value,
                'incident_id' => fake()->randomNumber(4),
                'monitor_id' => fake()->randomNumber(3),
                'timestamp' => now()->toIso8601String(),
            ],
            'status' => WebhookDeliveryStatus::Pending,
            'attempts' => 0,
            'last_attempt_at' => null,
            'response_status_code' => null,
            'response_body' => null,
            'error_message' => null,
        ];
    }
}
