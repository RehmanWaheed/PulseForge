<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookSigner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'url' => fake()->url().'/webhook',
            'secret' => app(WebhookSigner::class)->generateSecret(),
            'events' => ['*'],
            'is_active' => true,
            'description' => 'Test Webhook Endpoint',
        ];
    }
}
