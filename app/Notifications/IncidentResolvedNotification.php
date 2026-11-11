<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IncidentResolvedNotification extends Notification implements ShouldQueue
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
        $duration = $this->incident->durationMinutes() ?? 0;

        $mail = (new MailMessage)
            ->success()
            ->subject(sprintf('✅ [RESOLVED] Service Recovered: %s', $monitorName))
            ->greeting('Service Outage Resolved')
            ->line(sprintf('Good news! **%s** has recovered and all health checks are passing normally.', $monitorName))
            ->line(sprintf('**Target URL:** %s', $monitorUrl))
            ->line(sprintf('**Total Downtime:** %d minute(s)', $duration))
            ->line(sprintf('**Resolved At:** %s (UTC)', $this->incident->resolved_at?->toDateTimeString() ?? now()->toDateTimeString()));

        if ($this->incident->root_cause_analysis !== null) {
            $mail->line('**AI Post-Mortem Summary:**')
                ->line($this->incident->root_cause_analysis);
        }

        return $mail->action('View Incidents', url('/api/v1/incidents'))
            ->line('Thank you for using PulseForge automated monitoring.');
    }
}
