<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\NotificationSchedule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Send a notification to a user
     */
    public function notify(
        User|int $user,
        string $type,
        string $title,
        ?string $body = null,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?object $notifiable = null,
        ?array $data = null,
        ?string $icon = null,
        ?string $iconColor = null
    ): ?Notification {
        $userId = $user instanceof User ? $user->id : $user;
        $category = Notification::getCategoryFromType($type);

        // Check user preferences
        $preference = $this->getPreference($userId, $category);
        if (!$preference->in_app) {
            return null;
        }

        // Check schedule (quiet hours, DND, etc.)
        $schedule = $this->getSchedule($userId);
        $shouldDelay = $schedule->shouldSuppressNotifications();

        // Get icon defaults if not provided
        if (!$icon || !$iconColor) {
            $defaults = Notification::getIconDefaults($type);
            $icon = $icon ?? $defaults['icon'];
            $iconColor = $iconColor ?? $defaults['color'];
        }

        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'icon' => $icon,
            'icon_color' => $iconColor,
            'action_url' => $actionUrl,
            'action_label' => $actionLabel,
            'notifiable_type' => $notifiable ? get_class($notifiable) : null,
            'notifiable_id' => $notifiable?->id ?? null,
            'data' => $data,
        ]);

        // Broadcast for real-time updates (unless suppressed)
        if (!$shouldDelay) {
            event(new NotificationCreated($notification));
        }

        // Queue email notification if enabled
        if ($preference->email) {
            $this->queueEmailNotification($notification, $preference);
        }

        return $notification;
    }

    /**
     * Send notification to multiple users
     */
    public function notifyMany(
        array|Collection $users,
        string $type,
        string $title,
        ?string $body = null,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?object $notifiable = null,
        ?array $data = null
    ): Collection {
        $notifications = collect();

        foreach ($users as $user) {
            $notification = $this->notify(
                $user,
                $type,
                $title,
                $body,
                $actionUrl,
                $actionLabel,
                $notifiable,
                $data
            );

            if ($notification) {
                $notifications->push($notification);
            }
        }

        return $notifications;
    }

    /**
     * Get user's notifications
     */
    public function getNotifications(
        int $userId,
        ?string $category = null,
        bool $unreadOnly = false,
        int $limit = 50,
        int $offset = 0
    ): Collection {
        $query = Notification::where('user_id', $userId)
            ->active()
            ->orderByDesc('created_at');

        if ($category) {
            $query->forCategory($category);
        }

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->skip($offset)->take($limit)->get();
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(int $userId, ?string $category = null): int
    {
        $query = Notification::where('user_id', $userId)
            ->active()
            ->unread();

        if ($category) {
            $query->forCategory($category);
        }

        return $query->count();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        return Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->update(['read_at' => now()]) > 0;
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(int $userId, ?string $category = null): int
    {
        $query = Notification::where('user_id', $userId)
            ->whereNull('read_at');

        if ($category) {
            $query->forCategory($category);
        }

        return $query->update(['read_at' => now()]);
    }

    /**
     * Archive a notification
     */
    public function archive(int $notificationId, int $userId): bool
    {
        return Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->update(['archived_at' => now()]) > 0;
    }

    /**
     * Delete old notifications
     */
    public function cleanupOldNotifications(int $daysToKeep = 90): int
    {
        return Notification::where('created_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }

    /**
     * Get user's notification preferences
     */
    public function getPreferences(int $userId): Collection
    {
        $preferences = NotificationPreference::where('user_id', $userId)
            ->get()
            ->keyBy('category');

        // Fill in defaults for missing categories
        $defaults = NotificationPreference::getDefaults();
        $result = collect();

        foreach (Notification::CATEGORIES as $category) {
            if ($preferences->has($category)) {
                $result[$category] = $preferences[$category];
            } else {
                $result[$category] = new NotificationPreference(array_merge(
                    ['user_id' => $userId, 'category' => $category],
                    $defaults[$category] ?? []
                ));
            }
        }

        return $result;
    }

    /**
     * Get preference for a specific category
     */
    public function getPreference(int $userId, string $category): NotificationPreference
    {
        $preference = NotificationPreference::where('user_id', $userId)
            ->where('category', $category)
            ->first();

        if (!$preference) {
            $defaults = NotificationPreference::getDefaults()[$category] ?? [];
            $preference = new NotificationPreference(array_merge(
                ['user_id' => $userId, 'category' => $category],
                $defaults
            ));
        }

        return $preference;
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(int $userId, array $preferences): void
    {
        DB::transaction(function () use ($userId, $preferences) {
            foreach ($preferences as $category => $settings) {
                NotificationPreference::updateOrCreate(
                    ['user_id' => $userId, 'category' => $category],
                    $settings
                );
            }
        });
    }

    /**
     * Get user's notification schedule
     */
    public function getSchedule(int $userId): NotificationSchedule
    {
        return NotificationSchedule::firstOrCreate(
            ['user_id' => $userId],
            ['timezone' => 'UTC']
        );
    }

    /**
     * Update notification schedule
     */
    public function updateSchedule(int $userId, array $settings): NotificationSchedule
    {
        return NotificationSchedule::updateOrCreate(
            ['user_id' => $userId],
            $settings
        );
    }

    /**
     * Queue email notification based on preference frequency
     */
    protected function queueEmailNotification(
        Notification $notification,
        NotificationPreference $preference
    ): void {
        $frequency = $preference->email_frequency ?? NotificationPreference::FREQUENCY_IMMEDIATE;

        if ($frequency === NotificationPreference::FREQUENCY_IMMEDIATE) {
            // TODO: Dispatch immediate email job
            // dispatch(new SendNotificationEmailJob($notification));
            return;
        }

        // Queue for digest
        $scheduledFor = match ($frequency) {
            NotificationPreference::FREQUENCY_HOURLY => now()->addHour()->startOfHour(),
            NotificationPreference::FREQUENCY_DAILY => now()->addDay()->startOfDay()->addHours(9),
            NotificationPreference::FREQUENCY_WEEKLY => now()->next('monday')->startOfDay()->addHours(9),
            default => now(),
        };

        DB::table('notification_email_queue')->insert([
            'user_id' => $notification->user_id,
            'notification_id' => $notification->id,
            'frequency' => $frequency,
            'scheduled_for' => $scheduledFor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
