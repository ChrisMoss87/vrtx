<?php

declare(strict_types=1);

namespace App\Domain\Call\Repositories;

/**
 * Repository interface for Call Queue Member operations.
 */
interface CallQueueMemberRepositoryInterface
{
    /**
     * Find a member by queue ID and user ID.
     */
    public function findByQueueAndUser(int $queueId, int $userId): ?array;

    /**
     * Get all members for a queue.
     *
     * @return array<int, array>
     */
    public function findByQueueId(int $queueId): array;

    /**
     * Get all queue memberships for a user.
     *
     * @return array<int, array>
     */
    public function findByUserId(int $userId): array;

    /**
     * Add a member to a queue.
     *
     * @param array<string, mixed> $data
     */
    public function create(int $queueId, array $data): array;

    /**
     * Update a member.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $queueId, int $userId, array $data): ?array;

    /**
     * Remove a member from a queue.
     */
    public function delete(int $queueId, int $userId): bool;

    /**
     * Delete all members for a queue.
     */
    public function deleteByQueueId(int $queueId): int;

    /**
     * Update member status.
     */
    public function setStatus(int $queueId, int $userId, string $status): bool;

    /**
     * Update status for all user's queue memberships.
     */
    public function setStatusForUser(int $userId, string $status, ?int $queueId = null): int;

    /**
     * Reset daily stats for all members in a queue.
     */
    public function resetDailyStats(int $queueId): int;

    /**
     * Check if user is already a member of the queue.
     */
    public function exists(int $queueId, int $userId): bool;
}
