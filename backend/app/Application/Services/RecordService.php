<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Modules\Entities\ModuleRecord;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use Exception;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RecordService
{
    public function __construct(
        private readonly ModuleRecordRepositoryInterface $recordRepository,
        private readonly ModuleRepositoryInterface $moduleRepository,
    ) {}

    /**
     * Create a new record for a module.
     *
     * @param  array<string, mixed>  $data  Field values keyed by api_name
     *
     * @throws RuntimeException If record creation fails
     */
    public function createRecord(int $moduleId, array $data, ?int $createdBy = null): ModuleRecord
    {
        $module = $this->moduleRepository->findById($moduleId);

        if (!$module) {
            throw new RuntimeException("Module not found with ID {$moduleId}.");
        }

        if (!$module->isActive()) {
            throw new RuntimeException('Cannot create records for inactive modules.');
        }

        DB::beginTransaction();

        try {
            // Create domain entity
            $record = ModuleRecord::create(
                $moduleId,
                $data,
                $createdBy ?? auth()->id()
            );

            // Persist using repository
            $savedRecord = $this->recordRepository->save($record);

            DB::commit();

            return $savedRecord;
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException("Failed to create record: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Update an existing record.
     *
     * @param  array<string, mixed>  $data  Field values keyed by api_name
     *
     * @throws RuntimeException If record update fails
     */
    public function updateRecord(int $moduleId, int $recordId, array $data, ?int $updatedBy = null): ModuleRecord
    {
        DB::beginTransaction();

        try {
            // Load module for validation
            $module = $this->moduleRepository->findById($moduleId);

            if (!$module) {
                throw new RuntimeException("Module not found with ID {$moduleId}.");
            }

            if (!$module->isActive()) {
                throw new RuntimeException('Cannot update records for inactive modules.');
            }

            // Get existing record
            $record = $this->recordRepository->findById($moduleId, $recordId);

            if (!$record) {
                throw new RuntimeException("Record not found with ID {$recordId}.");
            }

            // Update domain entity
            $record->update($data, $updatedBy ?? auth()->id());

            // Persist using repository
            $updatedRecord = $this->recordRepository->save($record);

            DB::commit();

            return $updatedRecord;
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException("Failed to update record: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete a record.
     *
     * @throws RuntimeException If record deletion fails
     */
    public function deleteRecord(int $moduleId, int $recordId): bool
    {
        DB::beginTransaction();

        try {
            $success = $this->recordRepository->delete($moduleId, $recordId);

            if (!$success) {
                throw new RuntimeException("Record not found with ID {$recordId}.");
            }

            DB::commit();

            return $success;
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException("Failed to delete record: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Bulk delete records.
     *
     * @param  array<int>  $recordIds
     *
     * @throws RuntimeException If bulk delete fails
     */
    public function bulkDeleteRecords(int $moduleId, array $recordIds): int
    {
        DB::beginTransaction();

        try {
            $deletedCount = $this->recordRepository->bulkDelete($moduleId, $recordIds);

            DB::commit();

            return $deletedCount;
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException("Failed to bulk delete records: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get a single record by ID.
     */
    public function getRecord(int $moduleId, int $recordId): ?ModuleRecord
    {
        return $this->recordRepository->findById($moduleId, $recordId);
    }

    /**
     * Get all records for a module with pagination.
     *
     * @param  array<string, mixed>  $filters  Array of field filters ['field_name' => ['operator' => 'value']]
     * @param  array<string, string>  $sort  Array of sort rules ['field_name' => 'asc|desc']
     * @return array{data: ModuleRecord[], total: int, per_page: int, current_page: int, last_page: int}
     */
    public function getRecords(
        int $moduleId,
        array $filters = [],
        array $sort = [],
        int $page = 1,
        int $perPage = 15
    ): array {
        return $this->recordRepository->findAll(
            $moduleId,
            $filters,
            $sort,
            $page,
            $perPage
        );
    }

    /**
     * Search records across multiple fields.
     *
     * @param  array<string>  $searchableFields  Field api_names to search
     * @return array{data: ModuleRecord[], total: int, per_page: int, current_page: int, last_page: int}
     */
    public function searchRecords(
        int $moduleId,
        string $searchTerm,
        array $searchableFields = [],
        int $page = 1,
        int $perPage = 15
    ): array {
        // If no fields specified, get all searchable fields from module
        if (empty($searchableFields)) {
            $module = $this->moduleRepository->findById($moduleId);

            if ($module) {
                foreach ($module->fields() as $field) {
                    if ($field->isSearchable()) {
                        $searchableFields[] = $field->apiName();
                    }
                }
            }
        }

        // Use search operator for global search
        $filters = [
            '_search' => [
                'operator' => 'search',
                'value' => $searchTerm,
                'fields' => $searchableFields,
            ],
        ];

        return $this->recordRepository->findAll(
            $moduleId,
            $filters,
            ['created_at' => 'desc'],
            $page,
            $perPage
        );
    }

    /**
     * Get records with specific field value.
     *
     * @return array{data: ModuleRecord[], total: int, per_page: int, current_page: int, last_page: int}
     */
    public function getRecordsByField(
        int $moduleId,
        string $fieldApiName,
        mixed $value,
        int $page = 1,
        int $perPage = 15
    ): array {
        $filters = [
            $fieldApiName => [
                'operator' => 'equals',
                'value' => $value,
            ],
        ];

        return $this->recordRepository->findAll(
            $moduleId,
            $filters,
            ['created_at' => 'desc'],
            $page,
            $perPage
        );
    }

    /**
     * Get record count for a module.
     */
    public function getRecordCount(int $moduleId, array $filters = []): int
    {
        return $this->recordRepository->count($moduleId, $filters);
    }

    /**
     * Check if a record exists.
     */
    public function recordExists(int $moduleId, int $recordId): bool
    {
        return $this->recordRepository->exists($moduleId, $recordId);
    }
}
