<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Repositories;

use App\Domain\Reporting\Entities\Dashboard;
use App\Domain\Shared\ValueObjects\PaginatedResult;

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
     * Find a dashboard by its ID and return as array.
     *
     * @return array<string, mixed>|null
     */
    public function findByIdAsArray(int $id, bool $includeWidgets = false, array $relations = []): ?array;

    /**
     * Find all dashboards.
     *
     * @return array<Dashboard>
     */
    public function findAll(): array;

    /**
     * Find all dashboards as arrays.
     *
     * @return array<array<string, mixed>>
     */
    public function findAllAsArrays(array $relations = []): array;

    /**
     * Find dashboards accessible by a user.
     *
     * @return array<Dashboard>
     */
    public function findAccessibleByUser(int $userId): array;

    /**
     * Find dashboards accessible by a user as arrays.
     *
     * @return array<array<string, mixed>>
     */
    public function findAccessibleByUserAsArrays(int $userId, array $relations = [], bool $withWidgetCount = false): array;

    /**
     * Find public dashboards.
     *
     * @return array<Dashboard>
     */
    public function findPublic(): array;

    /**
     * Find public dashboards as arrays.
     *
     * @return array<array<string, mixed>>
     */
    public function findPublicAsArrays(array $relations = []): array;

    /**
     * Find the default dashboard for a user.
     */
    public function findDefaultForUser(int $userId): ?Dashboard;

    /**
     * Find the default dashboard for a user as array.
     *
     * @return array<string, mixed>|null
     */
    public function findDefaultForUserAsArray(int $userId, array $relations = []): ?array;

    /**
     * Save a dashboard (insert or update).
     */
    public function save(Dashboard $dashboard): Dashboard;

    /**
     * Update a dashboard by ID with raw data.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function updateById(int $id, array $data): bool;

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

    /**
     * Get available widget types.
     *
     * @return array<string, mixed>
     */
    public function getAvailableWidgetTypes(): array;

    /**
     * Duplicate a dashboard for a user.
     *
     * @return array<string, mixed>
     */
    public function duplicateDashboard(int $dashboardId, int $userId, bool $includeWidgets = true): array;
}
