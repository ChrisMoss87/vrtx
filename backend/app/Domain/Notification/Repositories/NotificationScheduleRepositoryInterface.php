<?php

declare(strict_types=1);

namespace App\Domain\Notification\Repositories;

/**
 * Repository interface for NotificationSchedule.
 */
interface NotificationScheduleRepositoryInterface
{
    /**
     * Get schedule for a user, creating defaults if not exists.
     */
    public function getOrCreateForUser(int $userId): array;

    /**
     * Update schedule for a user.
     *
     * @param array<string, mixed> $settings
     */
    public function update(int $userId, array $settings): array;

    /**
     * Check if notifications should be suppressed for a user.
     */
    public function shouldSuppressNotifications(int $userId): bool;
}
