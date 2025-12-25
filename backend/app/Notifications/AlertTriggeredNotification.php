<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertTriggeredNotification extends Notification implements ShouldQueue
{
    use Queueable;
use Illuminate\Support\Facades\DB;

    protected AnalyticsAlertHistory $history;
    protected array $channels;

    public function __construct(AnalyticsAlertHistory $history, array $channels = ['mail', 'database'])
    {
        $this->history = $history;
        $this->channels = $channels;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channelMapping = [
            'email' => 'mail',
            'in_app' => 'database',
            // 'slack' => 'slack', // Can be enabled later
            // 'webhook' => WebhookChannel::class, // Can be enabled later
        ];

        return collect($this->channels)
            ->map(fn($c) => $channelMapping[$c] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $alert = $this->history->alert;
        $message = $this->history->getFormattedMessage();

        return (new MailMessage)
            ->subject("Alert Triggered: {$alert->name}")
            ->greeting("Alert: {$alert->name}")
            ->line($message)
            ->when($this->history->metric_value !== null, function ($mail) {
                $mail->line("Current Value: " . number_format($this->history->metric_value, 2));
            })
            ->when($this->history->threshold_value !== null, function ($mail) {
                $mail->line("Threshold: " . number_format($this->history->threshold_value, 2));
            })
            ->when($this->history->deviation_percent !== null, function ($mail) {
                $mail->line("Deviation: " . number_format($this->history->deviation_percent, 1) . "%");
            })
            ->action('View Alert', url('/alerts/' . $alert->id))
            ->line('This is an automated alert from your CRM analytics.');
    }

    /**
     * Get the array representation of the notification (for database channel).
     */
    public function toArray(object $notifiable): array
    {
        $alert = $this->history->alert;

        return [
            'type' => 'analytics_alert',
            'alert_id' => $alert->id,
            'alert_name' => $alert->name,
            'alert_type' => $alert->alert_type,
            'history_id' => $this->history->id,
            'message' => $this->history->getFormattedMessage(),
            'metric_value' => $this->history->metric_value,
            'threshold_value' => $this->history->threshold_value,
            'deviation_percent' => $this->history->deviation_percent,
            'triggered_at' => $this->history->created_at->toIso8601String(),
        ];
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(object $notifiable): string
    {
        return 'analytics_alert';
    }
}
