<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Repositories;

/**
 * Repository interface for Dashboard Widget Alerts.
 */
interface DashboardWidgetAlertRepositoryInterface
{
    /**
     * Find an alert by ID.
     */
    public function findById(int $id): ?array;

    /**
     * Find an alert by ID with widget relation.
     */
    public function findByIdWithWidget(int $id): ?array;

    /**
     * Get alerts for a widget.
     *
     * @return array<int, array>
     */
    public function findByWidgetId(int $widgetId): array;

    /**
     * Get active alerts for a dashboard.
     *
     * @return array<int, array>
     */
    public function findActiveByDashboardId(int $dashboardId): array;

    /**
     * Create a new alert.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): array;

    /**
     * Update an alert.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): ?array;

    /**
     * Delete an alert.
     */
    public function delete(int $id): bool;

    /**
     * Toggle alert active status.
     */
    public function toggleActive(int $id): ?array;

    /**
     * Check if alert is in cooldown period.
     */
    public function isInCooldown(int $id): bool;

    /**
     * Get alert widget data.
     */
    public function getWidget(int $alertId): ?array;

    /**
     * Get alert user data.
     */
    public function getUser(int $alertId): ?array;

    /**
     * Check if alert exists.
     */
    public function exists(int $id): bool;
}
