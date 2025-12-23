<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Repositories;

use App\Domain\Analytics\Entities\AnalyticsAlert;
use App\Domain\Shared\ValueObjects\PaginatedResult;

/**
 * Repository interface for AnalyticsAlert aggregate root.
 */
interface AnalyticsAlertRepositoryInterface
{
    // =========================================================================
    // BASIC CRUD
    // =========================================================================

    /**
     * Find an alert by ID (returns entity).
     */
    public function findById(int $id): ?AnalyticsAlert;

    /**
     * Find an alert by ID (returns array).
     */
    public function findByIdAsArray(int $id): ?array;

    /**
     * Find an alert by ID with relations (returns array).
     */
    public function findByIdWithRelations(int $id): ?array;

    /**
     * Create a new alert.
     */
    public function create(array $data): array;

    /**
     * Update an existing alert.
     */
    public function update(int $id, array $data): array;

    /**
     * Save an alert entity (create or update).
     */
    public function save(AnalyticsAlert $alert): AnalyticsAlert;

    /**
     * Delete an alert.
     */
    public function delete(int $id): bool;

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * List alerts with filters and pagination.
     */
    public function findWithFilters(array $filters, int $perPage = 25): PaginatedResult;

    /**
     * Get alerts that are due for checking.
     *
     * @return array<AnalyticsAlert>
     */
    public function getDueForCheck(): array;

    /**
     * Get alerts for a specific user (returns entities).
     *
     * @return array<AnalyticsAlert>
     */
    public function getForUser(int $userId): array;

    /**
     * Get alerts for a specific user (returns arrays).
     */
    public function getForUserAsArray(int $userId): array;

    /**
     * Get active alerts count for a user.
     */
    public function getActiveCount(?int $userId = null): int;

    /**
     * Get total alerts count for a user.
     */
    public function getTotalCount(?int $userId = null): int;

    // =========================================================================
    // COMMAND METHODS
    // =========================================================================

    /**
     * Record that an alert was checked.
     */
    public function recordCheck(int $id): void;

    /**
     * Record that an alert was triggered.
     */
    public function recordTrigger(int $id): void;

    /**
     * Toggle alert active status.
     */
    public function toggleActive(int $id): array;
}
