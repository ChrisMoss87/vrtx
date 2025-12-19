<?php

declare(strict_types=1);

namespace App\Domain\Modules\Services;

use App\Domain\Modules\DTOs\ModuleRecordDTO;
use App\Domain\Modules\Entities\ModuleRecord;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;

class ModuleRecordService
{
    public function __construct(
        private readonly ModuleRecordRepositoryInterface $recordRepository,
        private readonly ModuleRepositoryInterface $moduleRepository,
        private readonly ValidationService $validationService
    ) {}

    /**
     * Get all records for a module with filtering, sorting, and pagination.
     */
    public function getRecords(
        int $moduleId,
        array $filters = [],
        array $sort = [],
        int $page = 1,
        int $perPage = 15
    ): array {
        return $this->recordRepository->findAll($moduleId, $filters, $sort, $page, $perPage);
    }

    /**
     * Get a single record by ID.
     */
    public function getRecordById(int $moduleId, int $recordId): ?ModuleRecord
    {
        return $this->recordRepository->findById($moduleId, $recordId);
    }

    /**
     * Create a new record.
     */
    public function createRecord(ModuleRecordDTO $dto): ModuleRecord
    {
        // Get module definition
        $module = $this->moduleRepository->findById($dto->moduleId);

        if (!$module) {
            throw new \DomainException("Module not found.");
        }

        // Validate data against module fields
        $this->validationService->validateRecordData($module, $dto->data);

        // Create entity
        $record = ModuleRecord::create(
            moduleId: $dto->moduleId,
            data: $dto->data
        );

        return $this->recordRepository->save($record);
    }

    /**
     * Update an existing record.
     */
    public function updateRecord(int $moduleId, int $recordId, array $data): ModuleRecord
    {
        // Get module definition
        $module = $this->moduleRepository->findById($moduleId);

        if (!$module) {
            throw new \DomainException("Module not found.");
        }

        // Get existing record
        $record = $this->recordRepository->findById($moduleId, $recordId);

        if (!$record) {
            throw new \DomainException("Record not found.");
        }

        // Validate data
        $this->validationService->validateRecordData($module, $data);

        // Update record
        $record->updateData($data);

        return $this->recordRepository->save($record);
    }

    /**
     * Delete a record.
     */
    public function deleteRecord(int $moduleId, int $recordId): bool
    {
        return $this->recordRepository->delete($moduleId, $recordId);
    }

    /**
     * Bulk delete records.
     */
    public function bulkDeleteRecords(int $moduleId, array $recordIds): int
    {
        return $this->recordRepository->bulkDelete($moduleId, $recordIds);
    }

    /**
     * Count records with optional filters.
     */
    public function countRecords(int $moduleId, array $filters = []): int
    {
        return $this->recordRepository->count($moduleId, $filters);
    }
}
