<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Repositories;

use App\Domain\Reporting\Entities\Report;
use App\Domain\Reporting\ValueObjects\ReportType;

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
     * Find all reports.
     *
     * @return array<Report>
     */
    public function findAll(): array;

    /**
     * Find reports for a module.
     *
     * @return array<Report>
     */
    public function findByModule(int $moduleId): array;

    /**
     * Find reports accessible by a user.
     *
     * @return array<Report>
     */
    public function findAccessibleByUser(int $userId): array;

    /**
     * Find public reports.
     *
     * @return array<Report>
     */
    public function findPublic(): array;

    /**
     * Find favorite reports for a user.
     *
     * @return array<Report>
     */
    public function findFavoritesByUser(int $userId): array;

    /**
     * Find reports by type.
     *
     * @return array<Report>
     */
    public function findByType(ReportType $type): array;

    /**
     * Find scheduled reports that should run.
     *
     * @return array<Report>
     */
    public function findScheduledForExecution(): array;

    /**
     * Save a report (insert or update).
     */
    public function save(Report $report): Report;

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
}
