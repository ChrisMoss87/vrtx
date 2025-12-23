<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Repositories;

use App\Domain\Analytics\Entities\AnalyticsAlertHistory;
use App\Domain\Shared\ValueObjects\PaginatedResult;

/**
 * Repository interface for AnalyticsAlertHistory.
 */
interface AnalyticsAlertHistoryRepositoryInterface
{
    // =========================================================================
    // BASIC CRUD
    // =========================================================================

    /**
     * Find history entry by ID (returns entity).
     */
    public function findById(int $id): ?AnalyticsAlertHistory;

    /**
     * Find history entry by ID (returns array).
     */
    public function findByIdAsArray(int $id): ?array;

    /**
     * Create a new history entry.
     */
    public function create(array $data): array;

    /**
     * Save a history entry entity (create or update).
     */
    public function save(AnalyticsAlertHistory $history): AnalyticsAlertHistory;

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Get history for a specific alert with pagination.
     */
    public function findForAlert(int $alertId, int $perPage = 20): PaginatedResult;

    /**
     * Get history for a specific alert (returns entities).
     *
     * @return array<AnalyticsAlertHistory>
     */
    public function getForAlert(int $alertId, int $limit = 50): array;

    /**
     * Get history for a specific alert (returns arrays).
     */
    public function getForAlertAsArray(int $alertId, int $limit = 50): array;

    /**
     * Get historical metric values for an alert.
     *
     * @return array<int, float>
     */
    public function getHistoricalValues(int $alertId, int $periods): array;

    /**
     * Get unacknowledged alerts for a user (returns entities).
     *
     * @return array<AnalyticsAlertHistory>
     */
    public function getUnacknowledgedForUser(int $userId): array;

    /**
     * Get unacknowledged alerts for a user (returns arrays).
     */
    public function getUnacknowledgedForUserAsArray(int $userId): array;

    /**
     * Get count triggered today.
     */
    public function getTriggeredTodayCount(?int $userId = null): int;

    /**
     * Get unacknowledged count.
     */
    public function getUnacknowledgedCount(?int $userId = null): int;

    /**
     * Get baseline from historical data.
     */
    public function calculateBaseline(int $alertId, int $periods): float;

    /**
     * Get comparison value for period comparison.
     */
    public function getComparisonValue(int $alertId, int $periodDays): ?float;

    // =========================================================================
    // COMMAND METHODS
    // =========================================================================

    /**
     * Acknowledge an alert.
     */
    public function acknowledge(int $id, int $userId, ?string $note = null): void;
}
