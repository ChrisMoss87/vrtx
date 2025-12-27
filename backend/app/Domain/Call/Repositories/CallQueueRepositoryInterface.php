<?php

declare(strict_types=1);

namespace App\Domain\Call\Repositories;

/**
 * Repository interface for Call Queue operations.
 */
interface CallQueueRepositoryInterface
{
    /**
     * Get all queues with provider and members.
     *
     * @return array<int, array>
     */
    public function findAllWithRelations(): array;

    /**
     * Find a queue by ID.
     */
    public function findById(int $id): ?array;

    /**
     * Find a queue by ID with provider and members.
     */
    public function findByIdWithRelations(int $id): ?array;

    /**
     * Create a new queue.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): array;

    /**
     * Update a queue.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): ?array;

    /**
     * Delete a queue and its members.
     */
    public function delete(int $id): bool;

    /**
     * Toggle queue active status.
     */
    public function toggleActive(int $id): ?array;

    /**
     * Check if queue exists.
     */
    public function exists(int $id): bool;

    /**
     * Get online agent count for a queue.
     */
    public function getOnlineAgentCount(int $queueId): int;

    /**
     * Check if queue is within business hours.
     */
    public function isWithinBusinessHours(int $queueId): bool;
}
