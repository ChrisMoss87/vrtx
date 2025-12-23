<?php

declare(strict_types=1);

namespace App\Domain\LeadScoring\Repositories;

use App\Domain\LeadScoring\Entities\ScoringModel;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface ScoringModelRepositoryInterface
{
    public function findById(int $id): ?ScoringModel;

    public function findAll(): array;

    public function save(ScoringModel $entity): ScoringModel;

    public function delete(int $id): bool;

    // Scoring Model Query Methods
    public function listScoringModels(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult;

    public function getScoringModelWithFactors(int $modelId): ?array;

    public function getDefaultModelForModule(string $module): ?array;

    public function createScoringModel(array $data): array;

    public function updateScoringModel(int $modelId, array $data): array;

    public function deleteScoringModel(int $modelId): bool;

    public function duplicateScoringModel(int $modelId): array;

    public function activateScoringModel(int $modelId): array;

    public function archiveScoringModel(int $modelId): array;

    public function setModelAsDefault(int $modelId): array;

    // Scoring Factor Methods
    public function addFactor(int $modelId, array $data): array;

    public function updateFactor(int $factorId, array $data): array;

    public function deleteFactor(int $factorId): bool;

    public function reorderFactors(int $modelId, array $factorOrder): array;

    public function toggleFactorActive(int $factorId): array;

    // Lead Score Methods
    public function listLeadScores(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult;

    public function getLeadScore(int $scoreId): ?array;

    public function getScoreForRecord(string $module, int $recordId, ?int $modelId = null): ?array;

    public function calculateScore(string $module, int $recordId, ?int $modelId = null): array;

    public function bulkCalculateScores(string $module, array $recordIds, ?int $modelId = null): array;

    public function recalculateAllScores(int $modelId): int;

    public function getScoreHistory(int $scoreId, int $limit = 30): array;

    public function getScoreTrend(int $scoreId, int $days = 30): array;

    // Analytics Methods
    public function getModelStats(int $modelId): array;

    public function getTopScoredRecords(string $module, int $limit = 10, ?int $modelId = null): array;

    public function getScoreDistribution(string $module, ?int $modelId = null): array;

    public function getConversionAnalysis(string $module, string $conversionField, ?int $modelId = null): array;

    public function getScoreChangesOverTime(int $modelId, int $days = 30): array;

    public function getScoreImprovements(string $module, int $days = 7, ?int $modelId = null): array;
}
