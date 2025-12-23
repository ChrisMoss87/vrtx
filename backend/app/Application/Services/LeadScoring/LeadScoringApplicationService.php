<?php

declare(strict_types=1);

namespace App\Application\Services\LeadScoring;

use App\Domain\LeadScoring\Repositories\ScoringModelRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;

class LeadScoringApplicationService
{
    public function __construct(
        private ScoringModelRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // SCORING MODEL USE CASES
    // =========================================================================

    /**
     * List scoring models
     */
    public function listScoringModels(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        return $this->repository->listScoringModels($filters, $perPage, $page);
    }

    /**
     * Get a scoring model with factors
     */
    public function getScoringModel(int $modelId): ?array
    {
        return $this->repository->getScoringModelWithFactors($modelId);
    }

    /**
     * Get default model for a module
     */
    public function getDefaultModel(string $module): ?array
    {
        return $this->repository->getDefaultModelForModule($module);
    }

    /**
     * Create a scoring model
     */
    public function createScoringModel(array $data): array
    {
        return $this->repository->createScoringModel($data);
    }

    /**
     * Update a scoring model
     */
    public function updateScoringModel(int $modelId, array $data): array
    {
        return $this->repository->updateScoringModel($modelId, $data);
    }

    /**
     * Delete a scoring model
     */
    public function deleteScoringModel(int $modelId): bool
    {
        return $this->repository->deleteScoringModel($modelId);
    }

    /**
     * Duplicate a scoring model
     */
    public function duplicateScoringModel(int $modelId): array
    {
        return $this->repository->duplicateScoringModel($modelId);
    }

    /**
     * Activate a scoring model
     */
    public function activateScoringModel(int $modelId): array
    {
        return $this->repository->activateScoringModel($modelId);
    }

    /**
     * Archive a scoring model
     */
    public function archiveScoringModel(int $modelId): array
    {
        return $this->repository->archiveScoringModel($modelId);
    }

    /**
     * Set model as default for its module
     */
    public function setAsDefault(int $modelId): array
    {
        return $this->repository->setModelAsDefault($modelId);
    }

    // =========================================================================
    // SCORING FACTOR USE CASES
    // =========================================================================

    /**
     * Add factor to a model
     */
    public function addFactor(int $modelId, array $data): array
    {
        return $this->repository->addFactor($modelId, $data);
    }

    /**
     * Update a factor
     */
    public function updateFactor(int $factorId, array $data): array
    {
        return $this->repository->updateFactor($factorId, $data);
    }

    /**
     * Delete a factor
     */
    public function deleteFactor(int $factorId): bool
    {
        return $this->repository->deleteFactor($factorId);
    }

    /**
     * Reorder factors
     */
    public function reorderFactors(int $modelId, array $factorOrder): array
    {
        return $this->repository->reorderFactors($modelId, $factorOrder);
    }

    /**
     * Toggle factor active status
     */
    public function toggleFactorActive(int $factorId): array
    {
        return $this->repository->toggleFactorActive($factorId);
    }

    // =========================================================================
    // LEAD SCORE USE CASES
    // =========================================================================

    /**
     * List lead scores
     */
    public function listLeadScores(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        return $this->repository->listLeadScores($filters, $perPage, $page);
    }

    /**
     * Get lead score for a record
     */
    public function getLeadScore(int $scoreId): ?array
    {
        return $this->repository->getLeadScore($scoreId);
    }

    /**
     * Get score for a specific record
     */
    public function getScoreForRecord(string $module, int $recordId, ?int $modelId = null): ?array
    {
        return $this->repository->getScoreForRecord($module, $recordId, $modelId);
    }

    /**
     * Calculate score for a record
     */
    public function calculateScore(string $module, int $recordId, ?int $modelId = null): array
    {
        return $this->repository->calculateScore($module, $recordId, $modelId);
    }

    /**
     * Bulk calculate scores for multiple records
     */
    public function bulkCalculateScores(string $module, array $recordIds, ?int $modelId = null): array
    {
        return $this->repository->bulkCalculateScores($module, $recordIds, $modelId);
    }

    /**
     * Recalculate all scores for a model
     */
    public function recalculateAllScores(int $modelId): int
    {
        return $this->repository->recalculateAllScores($modelId);
    }

    /**
     * Get score history for a record
     */
    public function getScoreHistory(int $scoreId, int $limit = 30): array
    {
        return $this->repository->getScoreHistory($scoreId, $limit);
    }

    /**
     * Get score trend for a record
     */
    public function getScoreTrend(int $scoreId, int $days = 30): array
    {
        return $this->repository->getScoreTrend($scoreId, $days);
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get scoring model statistics
     */
    public function getModelStats(int $modelId): array
    {
        return $this->repository->getModelStats($modelId);
    }

    /**
     * Get top scored records
     */
    public function getTopScoredRecords(string $module, int $limit = 10, ?int $modelId = null): array
    {
        return $this->repository->getTopScoredRecords($module, $limit, $modelId);
    }

    /**
     * Get score distribution for a module
     */
    public function getScoreDistribution(string $module, ?int $modelId = null): array
    {
        return $this->repository->getScoreDistribution($module, $modelId);
    }

    /**
     * Get conversion analysis by score range
     */
    public function getConversionAnalysis(string $module, string $conversionField, ?int $modelId = null): array
    {
        return $this->repository->getConversionAnalysis($module, $conversionField, $modelId);
    }

    /**
     * Get score changes over time
     */
    public function getScoreChangesOverTime(int $modelId, int $days = 30): array
    {
        return $this->repository->getScoreChangesOverTime($modelId, $days);
    }

    /**
     * Identify score improvements
     */
    public function getScoreImprovements(string $module, int $days = 7, ?int $modelId = null): array
    {
        return $this->repository->getScoreImprovements($module, $days, $modelId);
    }
}
