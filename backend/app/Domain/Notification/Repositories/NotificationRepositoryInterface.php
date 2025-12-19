<?php

declare(strict_types=1);

namespace App\Domain\Notification\Repositories;

use App\Domain\Notification\Entities\Notification;

/**
 * Repository interface for Notification aggregate root.
 */
interface NotificationRepositoryInterface
{
    /**
     * Find a notification by ID.
     */
    public function findById(int $id): ?Notification;

    /**
     * Find a notification by ID for a specific user.
     */
    public function findByIdForUser(int $id, int $userId): ?Notification;

    /**
     * Get notifications for a user with filtering options.
     *
     * @return array<Notification>
     */
    public function getForUser(
        int $userId,
        ?string $category = null,
        bool $unreadOnly = false,
        int $limit = 50,
        int $offset = 0
    ): array;

    /**
     * Get unread notification count for a user.
     */
    public function getUnreadCount(int $userId, ?string $category = null): int;

    /**
     * Save a notification (create or update).
     */
    public function save(Notification $notification): Notification;

    /**
     * Mark a notification as read.
     */
    public function markAsRead(int $id, int $userId): bool;

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(int $userId, ?string $category = null): int;

    /**
     * Archive a notification.
     */
    public function archive(int $id, int $userId): bool;

    /**
     * Delete old notifications.
     */
    public function deleteOlderThan(int $days): int;
}
