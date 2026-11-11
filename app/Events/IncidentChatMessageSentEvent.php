<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\IncidentChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentChatMessageSentEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public IncidentChatMessage $chatMessage
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(sprintf(
                'tenant.%d.incident.%d',
                $this->chatMessage->tenant_id,
                $this->chatMessage->incident_id
            )),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'IncidentChatMessageSent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->chatMessage->id,
            'tenant_id' => $this->chatMessage->tenant_id,
            'incident_id' => $this->chatMessage->incident_id,
            'user_id' => $this->chatMessage->user_id,
            'user_name' => $this->chatMessage->user->name,
            'user_email' => $this->chatMessage->user->email,
            'message' => $this->chatMessage->message,
            'created_at' => $this->chatMessage->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}

