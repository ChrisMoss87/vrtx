<?php

declare(strict_types=1);

namespace App\Application\Services\Goal;

use App\Domain\Goal\Repositories\GoalRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;

class GoalApplicationService
{
    public function __construct(
        private GoalRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // GOAL QUERY USE CASES
    // =========================================================================

    /**
     * List goals with filtering and pagination
     */
    public function listGoals(array $filters = [], int $perPage = 15): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->repository->listGoals($filters, $perPage, $page);
    }

    /**
     * Get a single goal with all related data
     */
    public function getGoal(int $goalId): ?array
    {
        return $this->repository->findById($goalId);
    }

    /**
     * Get goals for a specific user
     */
    public function getUserGoals(int $userId, bool $currentOnly = true): array
    {
        return $this->repository->getUserGoals($userId, $currentOnly);
    }

    /**
     * Get team goals
     */
    public function getTeamGoals(?int $teamId = null): array
    {
        return $this->repository->getTeamGoals($teamId);
    }

    /**
     * Get company-wide goals
     */
    public function getCompanyGoals(): array
    {
        return $this->repository->getCompanyGoals();
    }

    /**
     * Get goal progress history
     */
    public function getGoalProgressHistory(int $goalId, ?string $startDate = null, ?string $endDate = null): array
    {
        return $this->repository->getGoalProgressHistory($goalId, $startDate, $endDate);
    }

    /**
     * Get goals that are at risk (behind pace)
     */
    public function getAtRiskGoals(): array
    {
        return $this->repository->getAtRiskGoals();
    }

    /**
     * Get overdue goals
     */
    public function getOverdueGoals(): array
    {
        return $this->repository->getOverdueGoals();
    }

    /**
     * Get goal statistics
     */
    public function getGoalStats(?int $userId = null): array
    {
        return $this->repository->getGoalStats($userId);
    }

    // =========================================================================
    // GOAL COMMAND USE CASES
    // =========================================================================

    /**
     * Create a new goal
     */
    public function createGoal(array $data): array
    {
        $createdBy = $this->authContext->userId();
        if (!$createdBy) {
            throw new \RuntimeException('User must be authenticated to create a goal');
        }

        return $this->repository->createGoal($data, $createdBy);
    }

    /**
     * Update a goal
     */
    public function updateGoal(int $goalId, array $data): array
    {
        return $this->repository->updateGoal($goalId, $data);
    }

    /**
     * Delete a goal
     */
    public function deleteGoal(int $goalId): bool
    {
        return $this->repository->deleteGoal($goalId);
    }

    /**
     * Update goal progress
     */
    public function updateGoalProgress(int $goalId, float $newValue, ?string $source = null, ?int $sourceRecordId = null): array
    {
        return $this->repository->updateGoalProgress($goalId, $newValue, $source, $sourceRecordId);
    }

    /**
     * Add to goal progress
     */
    public function addGoalProgress(int $goalId, float $amount, ?string $source = null, ?int $sourceRecordId = null): array
    {
        return $this->repository->addGoalProgress($goalId, $amount, $source, $sourceRecordId);
    }

    /**
     * Pause a goal
     */
    public function pauseGoal(int $goalId): array
    {
        return $this->repository->pauseGoal($goalId);
    }

    /**
     * Resume a goal
     */
    public function resumeGoal(int $goalId): array
    {
        return $this->repository->resumeGoal($goalId);
    }

    /**
     * Mark goal as missed
     */
    public function markGoalAsMissed(int $goalId): array
    {
        return $this->repository->markGoalAsMissed($goalId);
    }

    /**
     * Add milestone to a goal
     */
    public function addMilestone(int $goalId, array $data): array
    {
        return $this->repository->addMilestone($goalId, $data);
    }

    /**
     * Update a milestone
     */
    public function updateMilestone(int $milestoneId, array $data): array
    {
        return $this->repository->updateMilestone($milestoneId, $data);
    }

    /**
     * Delete a milestone
     */
    public function deleteMilestone(int $milestoneId): bool
    {
        return $this->repository->deleteMilestone($milestoneId);
    }

    // =========================================================================
    // QUOTA QUERY USE CASES
    // =========================================================================

    /**
     * List quota periods
     */
    public function listQuotaPeriods(array $filters = []): array
    {
        return $this->repository->listQuotaPeriods($filters);
    }

    /**
     * Get a quota period with quotas
     */
    public function getQuotaPeriod(int $periodId): ?array
    {
        return $this->repository->getQuotaPeriod($periodId);
    }

    /**
     * Get current period for a type
     */
    public function getCurrentPeriod(string $type = 'quarter'): ?array
    {
        return $this->repository->getCurrentPeriod($type);
    }

    /**
     * List quotas with filtering
     */
    public function listQuotas(array $filters = [], int $perPage = 15): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->repository->listQuotas($filters, $perPage, $page);
    }

    /**
     * Get user quotas for current period
     */
    public function getUserQuotas(int $userId): array
    {
        return $this->repository->getUserQuotas($userId);
    }

    /**
     * Get team quotas
     */
    public function getTeamQuotas(?int $teamId = null): array
    {
        return $this->repository->getTeamQuotas($teamId);
    }

    /**
     * Get quota statistics
     */
    public function getQuotaStats(?int $periodId = null): array
    {
        return $this->repository->getQuotaStats($periodId);
    }

    // =========================================================================
    // QUOTA COMMAND USE CASES
    // =========================================================================

    /**
     * Create a quota period
     */
    public function createQuotaPeriod(array $data): array
    {
        return $this->repository->createQuotaPeriod($data);
    }

    /**
     * Update a quota period
     */
    public function updateQuotaPeriod(int $periodId, array $data): array
    {
        return $this->repository->updateQuotaPeriod($periodId, $data);
    }

    /**
     * Create a quota
     */
    public function createQuota(array $data): array
    {
        $createdBy = $this->authContext->userId();
        if (!$createdBy) {
            throw new \RuntimeException('User must be authenticated to create a quota');
        }

        return $this->repository->createQuota($data, $createdBy);
    }

    /**
     * Update a quota
     */
    public function updateQuota(int $quotaId, array $data): array
    {
        return $this->repository->updateQuota($quotaId, $data);
    }

    /**
     * Delete a quota
     */
    public function deleteQuota(int $quotaId): bool
    {
        return $this->repository->deleteQuota($quotaId);
    }

    /**
     * Update quota progress
     */
    public function updateQuotaProgress(int $quotaId, float $newValue): array
    {
        return $this->repository->updateQuotaProgress($quotaId, $newValue);
    }

    /**
     * Add to quota progress
     */
    public function addQuotaProgress(int $quotaId, float $amount): array
    {
        return $this->repository->addQuotaProgress($quotaId, $amount);
    }

    /**
     * Create snapshot for all active quotas
     */
    public function createQuotaSnapshots(?int $periodId = null): int
    {
        return $this->repository->createQuotaSnapshots($periodId);
    }

    /**
     * Bulk create quotas for users
     */
    public function bulkCreateQuotas(int $periodId, array $userQuotas): array
    {
        $createdBy = $this->authContext->userId();
        if (!$createdBy) {
            throw new \RuntimeException('User must be authenticated to create quotas');
        }

        return $this->repository->bulkCreateQuotas($periodId, $userQuotas, $createdBy);
    }

    // =========================================================================
    // LEADERBOARD USE CASES
    // =========================================================================

    /**
     * Get leaderboard for a period and metric
     */
    public function getLeaderboard(int $periodId, string $metricType, int $limit = 10): array
    {
        return $this->repository->getLeaderboard($periodId, $metricType, $limit);
    }

    /**
     * Get user's leaderboard position
     */
    public function getUserLeaderboardPosition(int $userId, int $periodId, string $metricType): ?array
    {
        return $this->repository->getUserLeaderboardPosition($userId, $periodId, $metricType);
    }

    /**
     * Recalculate leaderboard for a period and metric
     */
    public function recalculateLeaderboard(int $periodId, string $metricType): int
    {
        return $this->repository->recalculateLeaderboard($periodId, $metricType);
    }

    /**
     * Get all leaderboards for a period
     */
    public function getAllLeaderboards(int $periodId, int $limit = 10): array
    {
        return $this->repository->getAllLeaderboards($periodId, $limit);
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get goal attainment trend
     */
    public function getGoalAttainmentTrend(int $goalId): array
    {
        return $this->repository->getGoalAttainmentTrend($goalId);
    }

    /**
     * Get quota attainment trend
     */
    public function getQuotaAttainmentTrend(int $quotaId): array
    {
        return $this->repository->getQuotaAttainmentTrend($quotaId);
    }

    /**
     * Get team performance comparison
     */
    public function getTeamPerformanceComparison(int $periodId): array
    {
        return $this->repository->getTeamPerformanceComparison($periodId);
    }

    /**
     * Get historical quota performance
     */
    public function getHistoricalPerformance(int $userId, string $metricType, int $periods = 4): array
    {
        return $this->repository->getHistoricalPerformance($userId, $metricType, $periods);
    }

    /**
     * Check and update overdue goals
     */
    public function processOverdueGoals(): int
    {
        return $this->repository->processOverdueGoals();
    }
}
