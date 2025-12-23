<?php

declare(strict_types=1);

namespace App\Domain\ImportExport\Repositories;

use App\Domain\ImportExport\Entities\Import;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface ImportRepositoryInterface
{
    /**
     * Find import by ID.
     */
    public function findById(int $id): ?Import;

    /**
     * Find import by ID as array for backward compatibility.
     */
    public function findByIdAsArray(int $id): ?array;

    /**
     * Find import by ID with rows.
     */
    public function findByIdWithRows(int $id): ?array;

    /**
     * List imports with filtering and pagination.
     */
    public function listImports(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult;

    /**
     * Get import rows with pagination.
     */
    public function getImportRows(int $importId, array $filters = [], int $perPage = 50, int $page = 1): PaginatedResult;

    /**
     * Get failed rows for an import.
     */
    public function getFailedRows(int $importId): array;

    /**
     * Get import statistics.
     */
    public function getImportStats(int $importId): array;

    /**
     * Get user's import history.
     */
    public function getUserImportHistory(int $userId, int $limit = 10): array;

    /**
     * Create a new import.
     */
    public function create(array $data): array;

    /**
     * Update import.
     */
    public function update(int $id, array $data): array;

    /**
     * Save an import (insert or update).
     */
    public function save(Import $import): Import;

    /**
     * Delete import and its file.
     */
    public function delete(int $id): bool;

    /**
     * Mark import as started.
     */
    public function markAsStarted(int $id): void;

    /**
     * Mark import as completed.
     */
    public function markAsCompleted(int $id): void;

    /**
     * Mark import as failed.
     */
    public function markAsFailed(int $id, string $message): void;

    /**
     * Mark import as cancelled.
     */
    public function markAsCancelled(int $id): void;

    /**
     * Increment processed count.
     */
    public function incrementProcessed(int $id, string $status): void;

    /**
     * Get pending rows for an import.
     */
    public function getPendingRows(int $importId): array;

    /**
     * Delete import rows.
     */
    public function deleteImportRows(int $importId): void;

    /**
     * Get import error analysis.
     */
    public function getImportErrorAnalysis(int $importId): array;

    /**
     * Get activity summary.
     */
    public function getActivitySummary(?int $userId = null, ?string $period = 'month'): array;
}
