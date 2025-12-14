<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Repositories;

use App\Domain\Reporting\Entities\Dashboard;

/**
 * Repository interface for Dashboard aggregate root.
 */
interface DashboardRepositoryInterface
{
    /**
     * Find a dashboard by its ID.
     */
    public function findById(int $id, bool $includeWidgets = false): ?Dashboard;

    /**
     * Find all dashboards.
     *
     * @return array<Dashboard>
     */
    public function findAll(): array;

    /**
     * Find dashboards accessible by a user.
     *
     * @return array<Dashboard>
     */
    public function findAccessibleByUser(int $userId): array;

    /**
     * Find public dashboards.
     *
     * @return array<Dashboard>
     */
    public function findPublic(): array;

    /**
     * Find the default dashboard for a user.
     */
    public function findDefaultForUser(int $userId): ?Dashboard;

    /**
     * Save a dashboard (insert or update).
     */
    public function save(Dashboard $dashboard): Dashboard;

    /**
     * Delete a dashboard (soft delete).
     */
    public function delete(int $id): bool;

    /**
     * Permanently delete a dashboard.
     */
    public function forceDelete(int $id): bool;

    /**
     * Restore a soft-deleted dashboard.
     */
    public function restore(int $id): bool;

    /**
     * Unset default dashboard for a user (except the given dashboard ID).
     */
    public function unsetDefaultForUser(int $userId, ?int $exceptDashboardId = null): void;
}
