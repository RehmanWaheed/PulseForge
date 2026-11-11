<?php

declare(strict_types=1);

namespace App\Actions\Incidents;

use App\Events\IncidentChatMessageSentEvent;
use App\Models\Incident;
use App\Models\IncidentChatMessage;
use App\Models\User;

class SendIncidentChatMessageAction
{
    /**
     * Store and broadcast a new war room chat message for an incident.
     */
    public function __invoke(Incident $incident, User $user, string $message): IncidentChatMessage
    {
        /** @var IncidentChatMessage $chatMessage */
        $chatMessage = IncidentChatMessage::create([
            'tenant_id' => $incident->tenant_id,
            'incident_id' => $incident->id,
            'user_id' => $user->id,
            'message' => trim($message),
        ]);

        $chatMessage->load('user');

        IncidentChatMessageSentEvent::dispatch($chatMessage);

        return $chatMessage;
    }
}

