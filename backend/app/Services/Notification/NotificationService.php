<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Domain\Notification\Repositories\NotificationPreferenceRepositoryInterface;
use App\Domain\Notification\Repositories\NotificationScheduleRepositoryInterface;
use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
        private NotificationPreferenceRepositoryInterface $preferenceRepository,
        private NotificationScheduleRepositoryInterface $scheduleRepository,
    ) {}

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
    ): ?array {
        $userId = $user instanceof User ? $user->id : $user;
        $category = Notification::getCategoryFromType($type);

        // Check user preferences
        $preference = $this->getPreference($userId, $category);
        if (!($preference['in_app'] ?? true)) {
            return null;
        }

        // Check schedule (quiet hours, DND, etc.)
        $shouldDelay = $this->scheduleRepository->shouldSuppressNotifications($userId);

        // Get icon defaults if not provided
        if (!$icon || !$iconColor) {
            $defaults = Notification::getIconDefaults($type);
            $icon = $icon ?? $defaults['icon'];
            $iconColor = $iconColor ?? $defaults['color'];
        }

        $notificationData = $this->notificationRepository->create([
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
            $notification = Notification::find($notificationData['id']);
            if ($notification) {
                event(new NotificationCreated($notification));
            }
        }

        // Queue email notification if enabled
        if ($preference['email'] ?? false) {
            $this->queueEmailNotification($notificationData, $preference);
        }

        return $notificationData;
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
        return $this->notificationRepository->getForUser(
            $userId,
            $category,
            $unreadOnly,
            $limit,
            $offset
        );
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(int $userId, ?string $category = null): int
    {
        return $this->notificationRepository->getUnreadCount($userId, $category);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        return $this->notificationRepository->markAsRead($notificationId, $userId);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(int $userId, ?string $category = null): int
    {
        return $this->notificationRepository->markAllAsRead($userId, $category);
    }

    /**
     * Archive a notification
     */
    public function archive(int $notificationId, int $userId): bool
    {
        return $this->notificationRepository->archive($notificationId, $userId);
    }

    /**
     * Delete old notifications
     */
    public function cleanupOldNotifications(int $daysToKeep = 90): int
    {
        return $this->notificationRepository->deleteOlderThan($daysToKeep);
    }

    /**
     * Get user's notification preferences
     */
    public function getPreferences(int $userId): Collection
    {
        $preferences = $this->preferenceRepository->getForUser($userId);
        $defaults = $this->preferenceRepository->getDefaults();
        $result = collect();

        foreach (Notification::CATEGORIES as $category) {
            if ($preferences->has($category)) {
                $result[$category] = $preferences[$category];
            } else {
                $result[$category] = array_merge(
                    ['user_id' => $userId, 'category' => $category],
                    $defaults[$category] ?? []
                );
            }
        }

        return $result;
    }

    /**
     * Get preference for a specific category
     */
    public function getPreference(int $userId, string $category): array
    {
        $preference = $this->preferenceRepository->getForCategory($userId, $category);

        if (!$preference) {
            $defaults = $this->preferenceRepository->getDefaults()[$category] ?? [];
            $preference = array_merge(
                ['user_id' => $userId, 'category' => $category],
                $defaults
            );
        }

        return $preference;
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(int $userId, array $preferences): void
    {
        $this->preferenceRepository->updateMany($userId, $preferences);
    }

    /**
     * Get user's notification schedule
     */
    public function getSchedule(int $userId): array
    {
        return $this->scheduleRepository->getOrCreateForUser($userId);
    }

    /**
     * Update notification schedule
     */
    public function updateSchedule(int $userId, array $settings): array
    {
        return $this->scheduleRepository->update($userId, $settings);
    }

    /**
     * Queue email notification based on preference frequency
     */
    protected function queueEmailNotification(
        array $notification,
        array $preference
    ): void {
        $frequency = $preference['email_frequency'] ?? NotificationPreference::FREQUENCY_IMMEDIATE;

        if ($frequency === NotificationPreference::FREQUENCY_IMMEDIATE) {
            // TODO: Dispatch immediate email job
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
            'user_id' => $notification['user_id'],
            'notification_id' => $notification['id'],
            'frequency' => $frequency,
            'scheduled_for' => $scheduledFor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
