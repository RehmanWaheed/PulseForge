<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IncidentOpenedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Incident $incident
    ) {
        $this->onQueue('default');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $monitor = $this->incident->monitor;
        $monitorName = $monitor !== null ? $monitor->name : 'Monitored Service';
        $monitorUrl = $monitor !== null ? $monitor->url : 'N/A';

        return (new MailMessage)
            ->error()
            ->subject(sprintf('🚨 [CRITICAL] Service Outage: %s', $monitorName))
            ->greeting('Critical Outage Detected!')
            ->line(sprintf('PulseForge has detected a service outage for **%s**.', $monitorName))
            ->line(sprintf('**Target URL:** %s', $monitorUrl))
            ->line(sprintf('**Severity:** %s', strtoupper($this->incident->severity->value)))
            ->line(sprintf('**Status:** %s', ucfirst($this->incident->status->value)))
            ->line(sprintf('**Failure Reason:** %s', $this->incident->description ?? 'Consecutive health checks failed.'))
            ->line(sprintf('**Started At:** %s (UTC)', $this->incident->started_at->toDateTimeString()))
            ->action('View Incidents', url('/api/v1/incidents'))
            ->line('You will automatically receive a recovery email once health probes confirm the service is restored.');
    }
}
