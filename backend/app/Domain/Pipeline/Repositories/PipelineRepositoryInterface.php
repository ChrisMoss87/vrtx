<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\Repositories;

use App\Domain\Pipeline\Entities\Pipeline;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface PipelineRepositoryInterface
{
    // =========================================================================
    // BASIC CRUD (DDD-compliant)
    // =========================================================================

    /**
     * Find a pipeline by ID (returns domain entity).
     */
    public function findById(int $id): ?Pipeline;

    /**
     * Save a pipeline entity (create or update).
     */
    public function save(Pipeline $pipeline): Pipeline;

    /**
     * Find a pipeline by ID (returns array for backward compatibility).
     */
    public function findByIdAsArray(int $id): ?array;

    public function findByIdWithRelations(int $id): ?array;

    public function create(array $data): array;

    public function update(int $id, array $data): array;

    public function delete(int $id): bool;

    // =========================================================================
    // PIPELINE QUERY METHODS
    // =========================================================================

    /**
     * List pipelines with filtering and pagination.
     */
    public function findWithFilters(array $filters, int $perPage = 15): PaginatedResult;

    /**
     * Get all pipelines (no pagination).
     */
    public function findAll(bool $activeOnly = true): array;

    /**
     * Get pipelines for a specific module.
     */
    public function findForModule(int $moduleId, bool $activeOnly = true): array;

    /**
     * Get pipeline by module API name.
     */
    public function findByModuleApiName(string $moduleApiName): ?array;

    /**
     * Get pipeline with stage metrics (counts and values).
     */
    public function findWithMetrics(int $pipelineId, ?string $valueFieldName = null): ?array;

    /**
     * Get pipeline analytics.
     */
    public function getAnalytics(
        int $pipelineId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array;

    /**
     * Duplicate a pipeline.
     */
    public function duplicate(int $pipelineId, ?string $newName = null, int $userId): array;

    // =========================================================================
    // STAGE QUERY METHODS
    // =========================================================================

    /**
     * Get stages for a pipeline.
     */
    public function findStages(int $pipelineId): array;

    /**
     * Get a single stage.
     */
    public function findStageById(int $stageId): ?array;

    /**
     * Get records in a stage.
     */
    public function findRecordsInStage(int $stageId, int $perPage = 15): PaginatedResult;

    /**
     * Get rotting records (records in stage too long).
     */
    public function findRottingRecords(int $pipelineId): array;

    // =========================================================================
    // STAGE COMMAND METHODS
    // =========================================================================

    /**
     * Create a stage.
     */
    public function createStage(int $pipelineId, array $data): array;

    /**
     * Update a stage.
     */
    public function updateStage(int $stageId, array $data): array;

    /**
     * Delete a stage.
     */
    public function deleteStage(int $stageId): bool;

    /**
     * Reorder stages.
     */
    public function reorderStages(int $pipelineId, array $stageOrder): array;

    // =========================================================================
    // STAGE TRANSITION METHODS
    // =========================================================================

    /**
     * Move record to a different stage.
     */
    public function moveRecordToStage(
        int $recordId,
        int $toStageId,
        int $userId,
        ?string $reason = null
    ): array;

    /**
     * Get stage history for a record.
     */
    public function findRecordStageHistory(int $recordId, ?int $pipelineId = null): array;

    /**
     * Get stage velocity (average time from first stage to won).
     */
    public function getStageVelocity(
        int $pipelineId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array;

    /**
     * Get pipeline forecast based on probability.
     */
    public function getForecast(int $pipelineId, string $valueFieldName): array;
}
