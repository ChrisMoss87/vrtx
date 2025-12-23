<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Repositories;

use App\Domain\Reporting\Entities\Report;
use App\Domain\Reporting\ValueObjects\ReportType;
use App\Domain\Shared\ValueObjects\PaginatedResult;

/**
 * Repository interface for Report aggregate root.
 */
interface ReportRepositoryInterface
{
    /**
     * Find a report by its ID.
     */
    public function findById(int $id): ?Report;

    /**
     * Find a report by its ID and return as array.
     *
     * @return array<string, mixed>|null
     */
    public function findByIdAsArray(int $id, array $relations = []): ?array;

    /**
     * Find all reports.
     *
     * @return array<Report>
     */
    public function findAll(): array;

    /**
     * Find all reports as arrays.
     *
     * @return array<array<string, mixed>>
     */
    public function findAllAsArrays(array $relations = []): array;

    /**
     * Find reports for a module.
     *
     * @return array<Report>
     */
    public function findByModule(int $moduleId): array;

    /**
     * Find reports for a module as arrays.
     *
     * @return array<array<string, mixed>>
     */
    public function findByModuleAsArrays(int $moduleId, array $relations = []): array;

    /**
     * Find reports accessible by a user.
     *
     * @return array<Report>
     */
    public function findAccessibleByUser(int $userId): array;

    /**
     * Find reports accessible by a user as arrays.
     *
     * @return array<array<string, mixed>>
     */
    public function findAccessibleByUserAsArrays(int $userId, array $relations = []): array;

    /**
     * Find reports accessible by a user with pagination and filters.
     *
     * @param int $userId
     * @param int $page
     * @param int $perPage
     * @param array<string, mixed> $filters Keys: module_id, type, favorites, search
     * @param array<string> $relations
     * @return PaginatedResult
     */
    public function findAccessibleByUserPaginated(
        int $userId,
        int $page = 1,
        int $perPage = 20,
        array $filters = [],
        array $relations = []
    ): PaginatedResult;

    /**
     * Find public reports.
     *
     * @return array<Report>
     */
    public function findPublic(): array;

    /**
     * Find public reports as arrays.
     *
     * @return array<array<string, mixed>>
     */
    public function findPublicAsArrays(array $relations = []): array;

    /**
     * Find favorite reports for a user.
     *
     * @return array<Report>
     */
    public function findFavoritesByUser(int $userId): array;

    /**
     * Find favorite reports for a user as arrays.
     *
     * @return array<array<string, mixed>>
     */
    public function findFavoritesByUserAsArrays(int $userId, array $relations = []): array;

    /**
     * Find reports by type.
     *
     * @return array<Report>
     */
    public function findByType(ReportType $type): array;

    /**
     * Find reports by type as arrays.
     *
     * @return array<array<string, mixed>>
     */
    public function findByTypeAsArrays(ReportType $type, array $relations = []): array;

    /**
     * Find scheduled reports that should run.
     *
     * @return array<Report>
     */
    public function findScheduledForExecution(): array;

    /**
     * Find scheduled reports that should run as arrays.
     *
     * @return array<array<string, mixed>>
     */
    public function findScheduledForExecutionAsArrays(array $relations = []): array;

    /**
     * Save a report (insert or update).
     */
    public function save(Report $report): Report;

    /**
     * Update a report by ID with raw data.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function updateById(int $id, array $data): bool;

    /**
     * Delete a report (soft delete).
     */
    public function delete(int $id): bool;

    /**
     * Permanently delete a report.
     */
    public function forceDelete(int $id): bool;

    /**
     * Restore a soft-deleted report.
     */
    public function restore(int $id): bool;

    /**
     * Get available report types.
     *
     * @return array<string, mixed>
     */
    public function getAvailableTypes(): array;

    /**
     * Get available chart types.
     *
     * @return array<string, mixed>
     */
    public function getAvailableChartTypes(): array;

    /**
     * Get available aggregations.
     *
     * @return array<string, mixed>
     */
    public function getAvailableAggregations(): array;
}
