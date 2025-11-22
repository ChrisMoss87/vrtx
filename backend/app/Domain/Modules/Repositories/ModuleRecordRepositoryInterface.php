<?php

declare(strict_types=1);

namespace App\Domain\Modules\Repositories;

use App\Domain\Modules\Entities\ModuleRecord;

interface ModuleRecordRepositoryInterface
{
    /**
     * Find a record by ID within a specific module.
     */
    public function findById(int $moduleId, int $recordId): ?ModuleRecord;

    /**
     * Find all records for a module with optional filters, sorting, and pagination.
     *
     * @param  array<string, mixed>  $filters  Array of field filters ['field_name' => ['operator' => 'value']]
     * @param  array<string, string>  $sort  Array of sort rules ['field_name' => 'asc|desc']
     * @return array{data: ModuleRecord[], total: int, per_page: int, current_page: int, last_page: int}
     */
    public function findAll(
        int $moduleId,
        array $filters = [],
        array $sort = [],
        int $page = 1,
        int $perPage = 15
    ): array;

    /**
     * Save a module record (create or update).
     */
    public function save(ModuleRecord $record): ModuleRecord;

    /**
     * Delete a record by ID.
     */
    public function delete(int $moduleId, int $recordId): bool;

    /**
     * Bulk delete records by IDs.
     *
     * @param  array<int>  $recordIds
     */
    public function bulkDelete(int $moduleId, array $recordIds): int;

    /**
     * Count total records for a module with optional filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function count(int $moduleId, array $filters = []): int;

    /**
     * Check if a record exists.
     */
    public function exists(int $moduleId, int $recordId): bool;
}
