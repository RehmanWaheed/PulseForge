<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookEvent;
use App\Traits\BelongsToTenant;
use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $url
 * @property string $secret
 * @property array<int, string> $events
 * @property bool $is_active
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'url',
    'secret',
    'events',
    'is_active',
    'description',
])]
class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Outbound deliveries attempted for this endpoint.
     *
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Determine if this endpoint is subscribed to a particular event.
     */
    public function isSubscribedTo(WebhookEvent $event): bool
    {
        return in_array('*', $this->events, true) || in_array($event->value, $this->events, true);
    }
}
