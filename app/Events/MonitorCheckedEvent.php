<?php

declare(strict_types=1);

namespace App\Events;

use App\DTOs\MonitorCheckResultDTO;
use App\Models\Monitor;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MonitorCheckedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Monitor $monitor,
        public MonitorCheckResultDTO $result
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(sprintf('tenant.%d', $this->monitor->tenant_id)),
        ];
    }

    /**
     * Data to broadcast with the event.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'monitor_id' => $this->monitor->id,
            'status' => $this->monitor->status->value,
            'response_time_ms' => $this->result->responseTimeMs,
            'is_successful' => $this->result->isSuccessful,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
