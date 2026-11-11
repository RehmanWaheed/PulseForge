<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\IncidentCreatedEvent;
use App\Notifications\IncidentOpenedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendIncidentCreatedAlertListener implements ShouldQueue
{
    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'default';

    /**
     * Handle the incident created event.
     */
    public function handle(IncidentCreatedEvent $event): void
    {
        $tenant = $event->incident->tenant;
        if ($tenant === null) {
            return;
        }

        $users = $tenant->users()->get();

        if ($users->isNotEmpty()) {
            Notification::send($users, new IncidentOpenedNotification($event->incident));
        }
    }
}
