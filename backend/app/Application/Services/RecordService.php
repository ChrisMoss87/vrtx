<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Services\Workflow\WorkflowApplicationService;
use App\Domain\Modules\Entities\ModuleRecord;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Workflow\Services\ConditionEvaluationService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class RecordService
{
    public function __construct(
        private readonly ModuleRecordRepositoryInterface $recordRepository,
        private readonly ModuleRepositoryInterface $moduleRepository,
        private readonly ?WorkflowApplicationService $workflowService = null,
        private readonly ?ConditionEvaluationService $conditionEvaluator = null,
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

            // Trigger workflows for record creation (after commit)
            $this->triggerWorkflows('record_created', $savedRecord, null, $createdBy);

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

            // Store old data for workflow field change detection
            $oldData = $record->data();

            // Update domain entity
            $record->update($data, $updatedBy ?? auth()->id());

            // Persist using repository
            $updatedRecord = $this->recordRepository->save($record);

            DB::commit();

            // Trigger workflows for record update (after commit)
            $this->triggerWorkflows('record_updated', $updatedRecord, $oldData, $updatedBy);

            // Also trigger field change detection
            $this->triggerFieldChangeWorkflows($updatedRecord, $oldData, $updatedBy);

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
    public function deleteRecord(int $moduleId, int $recordId, ?int $deletedBy = null): bool
    {
        DB::beginTransaction();

        try {
            // Get record before deletion for workflow trigger
            $record = $this->recordRepository->findById($moduleId, $recordId);

            if (!$record) {
                throw new RuntimeException("Record not found with ID {$recordId}.");
            }

            // Store record data for workflow
            $recordData = $record->data();

            $success = $this->recordRepository->delete($moduleId, $recordId);

            if (!$success) {
                throw new RuntimeException("Failed to delete record with ID {$recordId}.");
            }

            DB::commit();

            // Trigger workflows for record deletion (after commit)
            $this->triggerDeleteWorkflows($moduleId, $recordId, $recordData, $deletedBy);

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
                foreach ($module->getFields() as $field) {
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

    /**
     * Get multiple records by their IDs.
     *
     * @param  array<int>  $recordIds
     * @return array<ModuleRecord>
     */
    public function getRecordsByIds(int $moduleId, array $recordIds): array
    {
        return $this->recordRepository->findByIds($moduleId, $recordIds);
    }

    /**
     * Trigger workflows for a record event using DDD services.
     */
    protected function triggerWorkflows(
        string $eventType,
        ModuleRecord $record,
        ?array $oldData = null,
        ?int $userId = null
    ): void {
        if (!$this->workflowService) {
            return;
        }

        try {
            // Find workflows that should trigger
            $triggeredWorkflows = $this->workflowService->findTriggeredWorkflows(
                moduleId: $record->moduleId(),
                eventType: $eventType,
                recordData: $record->data(),
                oldData: $oldData,
                isCreate: $eventType === 'record_created',
            );

            // Build context for workflow execution
            $context = $this->buildWorkflowContext($record, $oldData, $userId);

            foreach ($triggeredWorkflows as $workflow) {
                // Create execution which will dispatch the job
                $this->workflowService->createExecution(
                    workflowId: $workflow->getId(),
                    triggerType: $eventType,
                    recordId: $record->id(),
                    recordType: 'ModuleRecord',
                    contextData: $context,
                    triggeredByUserId: $userId,
                    dispatchJob: true,
                    delaySeconds: $workflow->delaySeconds(),
                );

                // Check stop on first match
                if ($workflow->stopOnFirstMatch()) {
                    break;
                }
            }
        } catch (Exception $e) {
            // Log but don't throw - workflow failures shouldn't break the main operation
            Log::error('Workflow trigger failed', [
                'event_type' => $eventType,
                'record_id' => $record->id(),
                'module_id' => $record->moduleId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Trigger field change workflows for a record.
     */
    protected function triggerFieldChangeWorkflows(
        ModuleRecord $record,
        array $oldData,
        ?int $userId = null
    ): void {
        if (!$this->workflowService) {
            return;
        }

        try {
            // Find field change workflows
            $triggeredWorkflows = $this->workflowService->findTriggeredWorkflows(
                moduleId: $record->moduleId(),
                eventType: 'field_changed',
                recordData: $record->data(),
                oldData: $oldData,
                isCreate: false,
            );

            // Build context with change information
            $context = $this->buildWorkflowContext($record, $oldData, $userId);

            foreach ($triggeredWorkflows as $workflow) {
                $this->workflowService->createExecution(
                    workflowId: $workflow->getId(),
                    triggerType: 'field_changed',
                    recordId: $record->id(),
                    recordType: 'ModuleRecord',
                    contextData: $context,
                    triggeredByUserId: $userId,
                    dispatchJob: true,
                    delaySeconds: $workflow->delaySeconds(),
                );

                if ($workflow->stopOnFirstMatch()) {
                    break;
                }
            }
        } catch (Exception $e) {
            Log::error('Field change workflow trigger failed', [
                'record_id' => $record->id(),
                'module_id' => $record->moduleId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Trigger delete workflows for a record.
     */
    protected function triggerDeleteWorkflows(
        int $moduleId,
        int $recordId,
        array $recordData,
        ?int $userId = null
    ): void {
        if (!$this->workflowService) {
            return;
        }

        try {
            // Build context for deleted record
            $context = [
                'record' => array_merge(['id' => $recordId, 'module_id' => $moduleId], $recordData),
                'record_id' => $recordId,
                'module_id' => $moduleId,
                'old_data' => $recordData,
                'changes' => [],
                'changed_fields' => [],
                'user_id' => $userId,
                'current_user' => $userId,
                'timestamp' => now()->toISOString(),
                'now' => [
                    'date' => now()->toDateString(),
                    'time' => now()->toTimeString(),
                    'datetime' => now()->toDateTimeString(),
                    'timestamp' => now()->timestamp,
                ],
                'is_deleted' => true,
                'step_outputs' => [],
            ];

            // Find delete workflows (using dummy record data since record is deleted)
            $triggeredWorkflows = $this->workflowService->findTriggeredWorkflows(
                moduleId: $moduleId,
                eventType: 'record_deleted',
                recordData: $recordData,
                oldData: null,
                isCreate: false,
            );

            foreach ($triggeredWorkflows as $workflow) {
                // Check conditions using the domain service
                if ($this->conditionEvaluator) {
                    $conditions = $workflow->conditions();
                    if (!empty($conditions) && !$this->conditionEvaluator->evaluate($conditions, $context)) {
                        continue;
                    }
                }

                $this->workflowService->createExecution(
                    workflowId: $workflow->getId(),
                    triggerType: 'record_deleted',
                    recordId: $recordId,
                    recordType: 'ModuleRecord',
                    contextData: $context,
                    triggeredByUserId: $userId,
                    dispatchJob: true,
                    delaySeconds: $workflow->delaySeconds(),
                );

                if ($workflow->stopOnFirstMatch()) {
                    break;
                }
            }
        } catch (Exception $e) {
            Log::error('Delete workflow trigger failed', [
                'record_id' => $recordId,
                'module_id' => $moduleId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build workflow execution context from a record.
     */
    private function buildWorkflowContext(ModuleRecord $record, ?array $oldData, ?int $userId): array
    {
        $newData = $record->data();
        $changes = [];
        $changedFields = [];

        if ($oldData !== null) {
            foreach ($newData as $key => $newValue) {
                $oldValue = $oldData[$key] ?? null;
                if ($oldValue !== $newValue) {
                    $changes[$key] = ['old' => $oldValue, 'new' => $newValue];
                    $changedFields[] = $key;
                }
            }

            // Check for removed fields
            foreach ($oldData as $key => $oldValue) {
                if (!array_key_exists($key, $newData)) {
                    $changes[$key] = ['old' => $oldValue, 'new' => null];
                    $changedFields[] = $key;
                }
            }
        }

        return [
            'record' => array_merge(['id' => $record->id(), 'module_id' => $record->moduleId()], $newData),
            'record_id' => $record->id(),
            'module_id' => $record->moduleId(),
            'old_data' => $oldData,
            'changes' => $changes,
            'changed_fields' => $changedFields,
            'user_id' => $userId,
            'current_user' => $userId,
            'timestamp' => now()->toISOString(),
            'now' => [
                'date' => now()->toDateString(),
                'time' => now()->toTimeString(),
                'datetime' => now()->toDateTimeString(),
                'timestamp' => now()->timestamp,
            ],
            'step_outputs' => [],
        ];
    }
}
