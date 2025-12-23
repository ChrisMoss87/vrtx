<?php

declare(strict_types=1);

namespace App\Domain\Goal\Repositories;

use App\Domain\Goal\Entities\Goal;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface GoalRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?Goal;

    public function save(Goal $entity): Goal;

    public function delete(int $id): bool;

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array;

    public function findAll(): array;

    // Query methods
    public function listGoals(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult;

    public function getUserGoals(int $userId, bool $currentOnly = true): array;

    public function getTeamGoals(?int $teamId = null): array;

    public function getCompanyGoals(): array;

    public function getAtRiskGoals(): array;

    public function getOverdueGoals(): array;

    public function getGoalStats(?int $userId = null): array;

    // Goal progress
    public function getGoalProgressHistory(int $goalId, ?string $startDate = null, ?string $endDate = null): array;

    // Quota methods
    public function listQuotaPeriods(array $filters = []): array;

    public function getQuotaPeriod(int $periodId): ?array;

    public function getCurrentPeriod(string $type = 'quarter'): ?array;

    public function listQuotas(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult;

    public function getUserQuotas(int $userId): array;

    public function getTeamQuotas(?int $teamId = null): array;

    public function getQuotaStats(?int $periodId = null): array;

    // Quota commands
    public function createQuotaPeriod(array $data): array;

    public function updateQuotaPeriod(int $periodId, array $data): array;

    public function createQuota(array $data, int $createdBy): array;

    public function updateQuota(int $quotaId, array $data): array;

    public function deleteQuota(int $quotaId): bool;

    public function updateQuotaProgress(int $quotaId, float $newValue): array;

    public function addQuotaProgress(int $quotaId, float $amount): array;

    public function createQuotaSnapshots(?int $periodId = null): int;

    public function bulkCreateQuotas(int $periodId, array $userQuotas, int $createdBy): array;

    // Leaderboard
    public function getLeaderboard(int $periodId, string $metricType, int $limit = 10): array;

    public function getUserLeaderboardPosition(int $userId, int $periodId, string $metricType): ?array;

    public function recalculateLeaderboard(int $periodId, string $metricType): int;

    public function getAllLeaderboards(int $periodId, int $limit = 10): array;

    // Analytics
    public function getGoalAttainmentTrend(int $goalId): array;

    public function getQuotaAttainmentTrend(int $quotaId): array;

    public function getTeamPerformanceComparison(int $periodId): array;

    public function getHistoricalPerformance(int $userId, string $metricType, int $periods = 4): array;

    public function processOverdueGoals(): int;

    // Goal commands
    public function createGoal(array $data, int $createdBy): array;

    public function updateGoal(int $goalId, array $data): array;

    public function deleteGoal(int $goalId): bool;

    public function updateGoalProgress(int $goalId, float $newValue, ?string $source = null, ?int $sourceRecordId = null): array;

    public function addGoalProgress(int $goalId, float $amount, ?string $source = null, ?int $sourceRecordId = null): array;

    public function pauseGoal(int $goalId): array;

    public function resumeGoal(int $goalId): array;

    public function markGoalAsMissed(int $goalId): array;

    // Milestone commands
    public function addMilestone(int $goalId, array $data): array;

    public function updateMilestone(int $milestoneId, array $data): array;

    public function deleteMilestone(int $milestoneId): bool;
}
