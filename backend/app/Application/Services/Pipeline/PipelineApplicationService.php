<?php

declare(strict_types=1);

namespace App\Application\Services\Pipeline;

use App\Domain\Pipeline\Repositories\PipelineRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;

class PipelineApplicationService
{
    public function __construct(
        private PipelineRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // PIPELINE QUERY USE CASES
    // =========================================================================

    /**
     * List pipelines with filtering
     */
    public function listPipelines(array $filters = [], int $perPage = 15): PaginatedResult
    {
        return $this->repository->findWithFilters($filters, $perPage);
    }

    /**
     * Get all pipelines (no pagination)
     */
    public function getAllPipelines(bool $activeOnly = true): array
    {
        return $this->repository->findAll($activeOnly);
    }

    /**
     * Get a single pipeline with stages
     */
    public function getPipeline(int $pipelineId): ?array
    {
        return $this->repository->findByIdWithRelations($pipelineId);
    }

    /**
     * Get pipelines for a module
     */
    public function getPipelinesForModule(int $moduleId, bool $activeOnly = true): array
    {
        return $this->repository->findForModule($moduleId, $activeOnly);
    }

    /**
     * Get pipeline by module API name
     */
    public function getPipelineByModuleApiName(string $moduleApiName): ?array
    {
        return $this->repository->findByModuleApiName($moduleApiName);
    }

    /**
     * Get pipeline with stage metrics (counts and values)
     */
    public function getPipelineWithMetrics(int $pipelineId, ?string $valueFieldName = null): ?array
    {
        return $this->repository->findWithMetrics($pipelineId, $valueFieldName);
    }

    /**
     * Get pipeline analytics
     */
    public function getPipelineAnalytics(int $pipelineId, ?string $startDate = null, ?string $endDate = null): array
    {
        return $this->repository->getAnalytics($pipelineId, $startDate, $endDate);
    }

    // =========================================================================
    // PIPELINE COMMAND USE CASES
    // =========================================================================

    /**
     * Create a new pipeline
     */
    public function createPipeline(array $data): array
    {
        return $this->repository->create([
            'name' => $data['name'],
            'module_id' => $data['module_id'],
            'stage_field_api_name' => $data['stage_field_api_name'],
            'is_active' => $data['is_active'] ?? true,
            'settings' => $data['settings'] ?? [],
            'stages' => $data['stages'] ?? [],
            'created_by' => $this->authContext->userId(),
            'updated_by' => $this->authContext->userId(),
        ]);
    }

    /**
     * Update a pipeline
     */
    public function updatePipeline(int $pipelineId, array $data): array
    {
        return $this->repository->update($pipelineId, [
            'name' => $data['name'] ?? null,
            'stage_field_api_name' => $data['stage_field_api_name'] ?? null,
            'is_active' => $data['is_active'] ?? null,
            'settings' => $data['settings'] ?? null,
            'updated_by' => $this->authContext->userId(),
        ]);
    }

    /**
     * Delete a pipeline
     */
    public function deletePipeline(int $pipelineId): bool
    {
        return $this->repository->delete($pipelineId);
    }

    /**
     * Activate a pipeline
     */
    public function activatePipeline(int $pipelineId): array
    {
        return $this->repository->update($pipelineId, [
            'is_active' => true,
            'updated_by' => $this->authContext->userId(),
        ]);
    }

    /**
     * Deactivate a pipeline
     */
    public function deactivatePipeline(int $pipelineId): array
    {
        return $this->repository->update($pipelineId, [
            'is_active' => false,
            'updated_by' => $this->authContext->userId(),
        ]);
    }

    /**
     * Duplicate a pipeline
     */
    public function duplicatePipeline(int $pipelineId, ?string $newName = null): array
    {
        return $this->repository->duplicate($pipelineId, $newName, $this->authContext->userId());
    }

    // =========================================================================
    // STAGE QUERY USE CASES
    // =========================================================================

    /**
     * Get stages for a pipeline
     */
    public function getStages(int $pipelineId): array
    {
        return $this->repository->findStages($pipelineId);
    }

    /**
     * Get a single stage
     */
    public function getStage(int $stageId): ?array
    {
        return $this->repository->findStageById($stageId);
    }

    /**
     * Get records in a stage
     */
    public function getRecordsInStage(int $stageId, int $perPage = 15): PaginatedResult
    {
        return $this->repository->findRecordsInStage($stageId, $perPage);
    }

    /**
     * Get rotting deals (records in stage too long)
     */
    public function getRottingRecords(int $pipelineId): array
    {
        return $this->repository->findRottingRecords($pipelineId);
    }

    // =========================================================================
    // STAGE COMMAND USE CASES
    // =========================================================================

    /**
     * Create a stage
     */
    public function createStage(int $pipelineId, array $data): array
    {
        return $this->repository->createStage($pipelineId, $data);
    }

    /**
     * Update a stage
     */
    public function updateStage(int $stageId, array $data): array
    {
        return $this->repository->updateStage($stageId, $data);
    }

    /**
     * Delete a stage
     */
    public function deleteStage(int $stageId): bool
    {
        return $this->repository->deleteStage($stageId);
    }

    /**
     * Reorder stages
     */
    public function reorderStages(int $pipelineId, array $stageOrder): array
    {
        return $this->repository->reorderStages($pipelineId, $stageOrder);
    }

    // =========================================================================
    // STAGE TRANSITION USE CASES
    // =========================================================================

    /**
     * Move record to a different stage
     */
    public function moveRecordToStage(
        int $recordId,
        int $toStageId,
        ?string $reason = null
    ): array {
        return $this->repository->moveRecordToStage(
            $recordId,
            $toStageId,
            $this->authContext->userId(),
            $reason
        );
    }

    /**
     * Bulk move records to a stage
     */
    public function bulkMoveToStage(array $recordIds, int $toStageId, ?string $reason = null): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($recordIds as $recordId) {
            try {
                $result = $this->moveRecordToStage($recordId, $toStageId, $reason);
                $results['success'][] = [
                    'record_id' => $recordId,
                    'history_id' => $result['history']['id'],
                ];
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'record_id' => $recordId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get stage history for a record
     */
    public function getRecordStageHistory(int $recordId, ?int $pipelineId = null): array
    {
        return $this->repository->findRecordStageHistory($recordId, $pipelineId);
    }

    /**
     * Get stage velocity (average time from first stage to won)
     */
    public function getStageVelocity(int $pipelineId, ?string $startDate = null, ?string $endDate = null): array
    {
        return $this->repository->getStageVelocity($pipelineId, $startDate, $endDate);
    }

    /**
     * Get pipeline forecast based on probability
     */
    public function getPipelineForecast(int $pipelineId, string $valueFieldName): array
    {
        return $this->repository->getForecast($pipelineId, $valueFieldName);
    }
}
