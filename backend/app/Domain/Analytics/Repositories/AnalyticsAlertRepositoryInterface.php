<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Repositories;

use App\Domain\Analytics\Entities\AnalyticsAlert;

/**
 * Repository interface for AnalyticsAlert aggregate root.
 */
interface AnalyticsAlertRepositoryInterface
{
    /**
     * Find an alert by ID.
     */
    public function findById(int $id): ?AnalyticsAlert;

    /**
     * Get alerts that are due for checking.
     *
     * @return array<AnalyticsAlert>
     */
    public function getDueForCheck(): array;

    /**
     * Get alerts for a specific user.
     *
     * @return array<AnalyticsAlert>
     */
    public function getForUser(int $userId): array;

    /**
     * Get active alerts count for a user.
     */
    public function getActiveCount(?int $userId = null): int;

    /**
     * Get total alerts count for a user.
     */
    public function getTotalCount(?int $userId = null): int;

    /**
     * Save an alert (create or update).
     */
    public function save(AnalyticsAlert $alert): AnalyticsAlert;

    /**
     * Delete an alert.
     */
    public function delete(int $id): bool;

    /**
     * Record that an alert was checked.
     */
    public function recordCheck(int $id): void;

    /**
     * Record that an alert was triggered.
     */
    public function recordTrigger(int $id): void;
}
