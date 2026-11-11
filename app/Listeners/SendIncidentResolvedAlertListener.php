<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\IncidentResolvedEvent;
use App\Notifications\IncidentResolvedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendIncidentResolvedAlertListener implements ShouldQueue
{
    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'default';

    /**
     * Handle the incident resolved event.
     */
    public function handle(IncidentResolvedEvent $event): void
    {
        $tenant = $event->incident->tenant;
        if ($tenant === null) {
            return;
        }

        $users = $tenant->users()->get();

        if ($users->isNotEmpty()) {
            Notification::send($users, new IncidentResolvedNotification($event->incident));
        }
    }
}

