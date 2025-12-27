<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Repositories;

/**
 * Repository interface for Dashboard Widget Alert History.
 */
interface DashboardWidgetAlertHistoryRepositoryInterface
{
    /**
     * Find a history entry by ID.
     */
    public function findById(int $id): ?array;

    /**
     * Get history for an alert.
     *
     * @return array<int, array>
     */
    public function findByAlertId(int $alertId, int $limit = 50): array;

    /**
     * Get history for a user, optionally filtered by dashboard.
     *
     * @return array<int, array>
     */
    public function findByUserId(int $userId, ?int $dashboardId = null, int $limit = 50): array;

    /**
     * Get unacknowledged history for a user.
     *
     * @return array<int, array>
     */
    public function findUnacknowledgedByUserId(int $userId): array;

    /**
     * Create a new history entry.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): array;

    /**
     * Acknowledge a history entry.
     */
    public function acknowledge(int $id, int $userId): bool;

    /**
     * Dismiss a history entry.
     */
    public function dismiss(int $id, int $userId): bool;

    /**
     * Get unacknowledged count for a user.
     */
    public function getUnacknowledgedCount(int $userId): int;

    /**
     * Get the last trigger value from history for an alert.
     */
    public function getLastTriggeredValue(int $alertId, int $cooldownMinutes): ?float;

    /**
     * Check if entry exists.
     */
    public function exists(int $id): bool;
}
