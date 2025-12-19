<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Repositories;

use App\Domain\Analytics\Entities\AnalyticsAlertHistory;

/**
 * Repository interface for AnalyticsAlertHistory.
 */
interface AnalyticsAlertHistoryRepositoryInterface
{
    /**
     * Find history entry by ID.
     */
    public function findById(int $id): ?AnalyticsAlertHistory;

    /**
     * Get history for a specific alert.
     *
     * @return array<AnalyticsAlertHistory>
     */
    public function getForAlert(int $alertId, int $limit = 50): array;

    /**
     * Get historical metric values for an alert.
     *
     * @return array<int, float>
     */
    public function getHistoricalValues(int $alertId, int $periods): array;

    /**
     * Get unacknowledged alerts for a user.
     *
     * @return array<AnalyticsAlertHistory>
     */
    public function getUnacknowledgedForUser(int $userId): array;

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

    /**
     * Save a history entry (create or update).
     */
    public function save(AnalyticsAlertHistory $history): AnalyticsAlertHistory;

    /**
     * Acknowledge an alert.
     */
    public function acknowledge(int $id, int $userId, ?string $note = null): void;
}
