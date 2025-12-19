<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsAlertHistory;
use App\Models\AnalyticsAlertSubscription;
use App\Models\User;
use App\Notifications\AlertTriggeredNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendAlertNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    protected ?int $alertHistoryId;

    public function __construct(?int $alertHistoryId = null)
    {
        $this->alertHistoryId = $alertHistoryId;
    }

    public function handle(): void
    {
        if ($this->alertHistoryId) {
            $this->sendSingleNotification($this->alertHistoryId);
        } else {
            $this->sendPendingNotifications();
        }
    }

    /**
     * Send notification for a single alert history entry.
     */
    protected function sendSingleNotification(int $historyId): void
    {
        $history = AnalyticsAlertHistory::with('alert')
            ->find($historyId);

        if (!$history || $history->notifications_sent) {
            return;
        }

        $this->notifyForHistory($history);
    }

    /**
     * Send all pending notifications.
     */
    protected function sendPendingNotifications(): void
    {
        $histories = AnalyticsAlertHistory::with('alert')
            ->where('status', AnalyticsAlertHistory::STATUS_TRIGGERED)
            ->whereNull('notifications_sent')
            ->where('created_at', '>=', now()->subHour())
            ->get();

        foreach ($histories as $history) {
            $this->notifyForHistory($history);
        }
    }

    /**
     * Send notifications for an alert history entry.
     */
    protected function notifyForHistory(AnalyticsAlertHistory $history): void
    {
        $alert = $history->alert;
        $notificationsSent = [];

        // Get recipients
        $recipientIds = $alert->getRecipientIds();
        $channels = $alert->getChannels();

        // Check subscriptions for overrides
        $subscriptions = AnalyticsAlertSubscription::where('alert_id', $alert->id)
            ->whereIn('user_id', $recipientIds)
            ->get()
            ->keyBy('user_id');

        foreach ($recipientIds as $userId) {
            $subscription = $subscriptions->get($userId);

            // Skip if muted
            if ($subscription && $subscription->isMuted()) {
                continue;
            }

            // Get effective channels
            $userChannels = $subscription ? $subscription->getEffectiveChannels() : $channels;

            $user = User::find($userId);
            if (!$user) {
                continue;
            }

            try {
                // Send notification through Laravel's notification system
                $user->notify(new AlertTriggeredNotification($history, $userChannels));

                $notificationsSent[] = [
                    'user_id' => $userId,
                    'channels' => $userChannels,
                    'sent_at' => now()->toIso8601String(),
                ];
            } catch (\Exception $e) {
                Log::error('Failed to send alert notification', [
                    'alert_id' => $alert->id,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Also send to email addresses if configured
        $emailAddresses = $alert->notification_config['email_addresses'] ?? [];
        if (!empty($emailAddresses) && in_array('email', $channels)) {
            try {
                Notification::route('mail', $emailAddresses)
                    ->notify(new AlertTriggeredNotification($history, ['email']));

                $notificationsSent[] = [
                    'emails' => $emailAddresses,
                    'channels' => ['email'],
                    'sent_at' => now()->toIso8601String(),
                ];
            } catch (\Exception $e) {
                Log::error('Failed to send alert email to addresses', [
                    'alert_id' => $alert->id,
                    'emails' => $emailAddresses,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update history with notifications sent
        $history->update([
            'notifications_sent' => $notificationsSent,
        ]);
    }

    public function tags(): array
    {
        return ['analytics', 'notifications'];
    }
}
