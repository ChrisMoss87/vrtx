<?php

declare(strict_types=1);

namespace App\Application\Services\Goal;

use App\Domain\Goal\Repositories\GoalRepositoryInterface;
use App\Models\Goal;
use App\Models\GoalMilestone;
use App\Models\GoalProgressLog;
use App\Models\LeaderboardEntry;
use App\Models\Quota;
use App\Models\QuotaPeriod;
use App\Models\QuotaSnapshot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GoalApplicationService
{
    public function __construct(
        private GoalRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // GOAL QUERY USE CASES
    // =========================================================================

    /**
     * List goals with filtering and pagination
     */
    public function listGoals(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Goal::query()->with(['user', 'milestones', 'progressLogs']);

        // Filter by type (individual, team, company)
        if (!empty($filters['goal_type'])) {
            $query->type($filters['goal_type']);
        }

        // Filter by user
        if (!empty($filters['user_id'])) {
            $query->forUser($filters['user_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter for active only
        if (!empty($filters['active_only'])) {
            $query->active();
        }

        // Filter for current period only
        if (!empty($filters['current_only'])) {
            $query->current();
        }

        // Filter by module
        if (!empty($filters['module_api_name'])) {
            $query->where('module_api_name', $filters['module_api_name']);
        }

        // Filter by metric type
        if (!empty($filters['metric_type'])) {
            $query->where('metric_type', $filters['metric_type']);
        }

        // Filter by date range
        if (!empty($filters['start_date_from'])) {
            $query->where('start_date', '>=', $filters['start_date_from']);
        }
        if (!empty($filters['end_date_to'])) {
            $query->where('end_date', '<=', $filters['end_date_to']);
        }

        // Sort
        $sortField = $filters['sort_by'] ?? 'end_date';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortField, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a single goal with all related data
     */
    public function getGoal(int $goalId): ?Goal
    {
        return Goal::with([
            'user',
            'createdBy',
            'milestones',
            'progressLogs' => fn($q) => $q->orderByDesc('log_date')->limit(30),
        ])->find($goalId);
    }

    /**
     * Get goals for a specific user
     */
    public function getUserGoals(int $userId, bool $currentOnly = true): Collection
    {
        $query = Goal::forUser($userId)
            ->with(['milestones'])
            ->active();

        if ($currentOnly) {
            $query->current();
        }

        return $query->orderBy('end_date')->get();
    }

    /**
     * Get team goals
     */
    public function getTeamGoals(?int $teamId = null): Collection
    {
        $query = Goal::type(Goal::TYPE_TEAM)
            ->active()
            ->current()
            ->with(['milestones']);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->orderBy('end_date')->get();
    }

    /**
     * Get company-wide goals
     */
    public function getCompanyGoals(): Collection
    {
        return Goal::type(Goal::TYPE_COMPANY)
            ->active()
            ->current()
            ->with(['milestones'])
            ->orderBy('end_date')
            ->get();
    }

    /**
     * Get goal progress history
     */
    public function getGoalProgressHistory(int $goalId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = GoalProgressLog::where('goal_id', $goalId)
            ->orderBy('log_date');

        if ($startDate) {
            $query->where('log_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('log_date', '<=', $endDate);
        }

        return $query->get();
    }

    /**
     * Get goals that are at risk (behind pace)
     */
    public function getAtRiskGoals(): Collection
    {
        return Goal::active()
            ->current()
            ->with(['user'])
            ->get()
            ->filter(function (Goal $goal) {
                // Calculate expected progress based on time elapsed
                $today = Carbon::today();
                $totalDays = $goal->start_date->diffInDays($goal->end_date) + 1;
                $daysElapsed = $goal->start_date->diffInDays($today) + 1;
                $expectedPercent = ($daysElapsed / $totalDays) * 100;

                // Goal is at risk if actual progress is less than 75% of expected
                return $goal->progress_percent < ($expectedPercent * 0.75);
            })
            ->values();
    }

    /**
     * Get overdue goals
     */
    public function getOverdueGoals(): Collection
    {
        return Goal::active()
            ->where('end_date', '<', Carbon::today())
            ->with(['user'])
            ->orderBy('end_date')
            ->get();
    }

    /**
     * Get goal statistics
     */
    public function getGoalStats(?int $userId = null): array
    {
        $query = Goal::query();

        if ($userId) {
            $query->forUser($userId);
        }

        $currentGoals = (clone $query)->current()->get();
        $achievedThisPeriod = (clone $query)
            ->where('status', Goal::STATUS_ACHIEVED)
            ->whereNotNull('achieved_at')
            ->where('achieved_at', '>=', Carbon::now()->startOfQuarter())
            ->count();

        $missedThisPeriod = (clone $query)
            ->where('status', Goal::STATUS_MISSED)
            ->where('end_date', '>=', Carbon::now()->startOfQuarter())
            ->count();

        $onTrackCount = $currentGoals->filter(function ($goal) {
            $today = Carbon::today();
            $totalDays = $goal->start_date->diffInDays($goal->end_date) + 1;
            $daysElapsed = $goal->start_date->diffInDays($today) + 1;
            $expectedPercent = ($daysElapsed / $totalDays) * 100;
            return $goal->progress_percent >= ($expectedPercent * 0.9);
        })->count();

        return [
            'total_active' => $currentGoals->where('status', Goal::STATUS_IN_PROGRESS)->count(),
            'achieved_this_quarter' => $achievedThisPeriod,
            'missed_this_quarter' => $missedThisPeriod,
            'on_track' => $onTrackCount,
            'at_risk' => $currentGoals->count() - $onTrackCount,
            'avg_attainment' => round($currentGoals->avg('attainment_percent') ?? 0, 1),
            'by_type' => [
                Goal::TYPE_INDIVIDUAL => (clone $query)->type(Goal::TYPE_INDIVIDUAL)->current()->count(),
                Goal::TYPE_TEAM => (clone $query)->type(Goal::TYPE_TEAM)->current()->count(),
                Goal::TYPE_COMPANY => (clone $query)->type(Goal::TYPE_COMPANY)->current()->count(),
            ],
        ];
    }

    // =========================================================================
    // GOAL COMMAND USE CASES
    // =========================================================================

    /**
     * Create a new goal
     */
    public function createGoal(array $data): Goal
    {
        return DB::transaction(function () use ($data) {
            $goal = Goal::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'goal_type' => $data['goal_type'],
                'user_id' => $data['user_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'metric_type' => $data['metric_type'],
                'metric_field' => $data['metric_field'] ?? null,
                'module_api_name' => $data['module_api_name'] ?? null,
                'target_value' => $data['target_value'],
                'currency' => $data['currency'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'current_value' => $data['current_value'] ?? 0,
                'attainment_percent' => 0,
                'status' => Goal::STATUS_IN_PROGRESS,
                'created_by' => Auth::id(),
            ]);

            // Create milestones if provided
            if (!empty($data['milestones'])) {
                foreach ($data['milestones'] as $index => $milestone) {
                    $goal->milestones()->create([
                        'name' => $milestone['name'],
                        'target_value' => $milestone['target_value'],
                        'target_date' => $milestone['target_date'] ?? null,
                        'display_order' => $milestone['display_order'] ?? $index,
                    ]);
                }
            }

            $goal->recalculate();

            return $goal->load(['milestones']);
        });
    }

    /**
     * Update a goal
     */
    public function updateGoal(int $goalId, array $data): Goal
    {
        $goal = Goal::findOrFail($goalId);

        $goal->update([
            'name' => $data['name'] ?? $goal->name,
            'description' => $data['description'] ?? $goal->description,
            'target_value' => $data['target_value'] ?? $goal->target_value,
            'end_date' => $data['end_date'] ?? $goal->end_date,
        ]);

        $goal->recalculate();

        return $goal->fresh(['milestones']);
    }

    /**
     * Delete a goal
     */
    public function deleteGoal(int $goalId): bool
    {
        $goal = Goal::findOrFail($goalId);

        return DB::transaction(function () use ($goal) {
            $goal->milestones()->delete();
            $goal->progressLogs()->delete();
            return $goal->delete();
        });
    }

    /**
     * Update goal progress
     */
    public function updateGoalProgress(int $goalId, float $newValue, ?string $source = null, ?int $sourceRecordId = null): Goal
    {
        $goal = Goal::findOrFail($goalId);
        $goal->updateProgress($newValue, $source, $sourceRecordId);
        return $goal->fresh(['milestones', 'progressLogs']);
    }

    /**
     * Add to goal progress
     */
    public function addGoalProgress(int $goalId, float $amount, ?string $source = null, ?int $sourceRecordId = null): Goal
    {
        $goal = Goal::findOrFail($goalId);
        $goal->addProgress($amount, $source, $sourceRecordId);
        return $goal->fresh(['milestones', 'progressLogs']);
    }

    /**
     * Pause a goal
     */
    public function pauseGoal(int $goalId): Goal
    {
        $goal = Goal::findOrFail($goalId);
        $goal->pause();
        return $goal;
    }

    /**
     * Resume a goal
     */
    public function resumeGoal(int $goalId): Goal
    {
        $goal = Goal::findOrFail($goalId);
        $goal->resume();
        return $goal;
    }

    /**
     * Mark goal as missed
     */
    public function markGoalAsMissed(int $goalId): Goal
    {
        $goal = Goal::findOrFail($goalId);
        $goal->markAsMissed();
        return $goal;
    }

    /**
     * Add milestone to a goal
     */
    public function addMilestone(int $goalId, array $data): GoalMilestone
    {
        $goal = Goal::findOrFail($goalId);

        $maxOrder = $goal->milestones()->max('display_order') ?? 0;

        return $goal->milestones()->create([
            'name' => $data['name'],
            'target_value' => $data['target_value'],
            'target_date' => $data['target_date'] ?? null,
            'display_order' => $data['display_order'] ?? $maxOrder + 1,
        ]);
    }

    /**
     * Update a milestone
     */
    public function updateMilestone(int $milestoneId, array $data): GoalMilestone
    {
        $milestone = GoalMilestone::findOrFail($milestoneId);

        $milestone->update([
            'name' => $data['name'] ?? $milestone->name,
            'target_value' => $data['target_value'] ?? $milestone->target_value,
            'target_date' => $data['target_date'] ?? $milestone->target_date,
        ]);

        return $milestone->fresh();
    }

    /**
     * Delete a milestone
     */
    public function deleteMilestone(int $milestoneId): bool
    {
        return GoalMilestone::findOrFail($milestoneId)->delete();
    }

    // =========================================================================
    // QUOTA QUERY USE CASES
    // =========================================================================

    /**
     * List quota periods
     */
    public function listQuotaPeriods(array $filters = []): Collection
    {
        $query = QuotaPeriod::query()->with(['quotas']);

        if (!empty($filters['type'])) {
            $query->type($filters['type']);
        }

        if (!empty($filters['active_only'])) {
            $query->active();
        }

        if (!empty($filters['current_only'])) {
            $query->current();
        }

        return $query->orderByDesc('start_date')->get();
    }

    /**
     * Get a quota period with quotas
     */
    public function getQuotaPeriod(int $periodId): ?QuotaPeriod
    {
        return QuotaPeriod::with([
            'quotas' => fn($q) => $q->with(['user', 'snapshots']),
        ])->find($periodId);
    }

    /**
     * Get current period for a type
     */
    public function getCurrentPeriod(string $type = QuotaPeriod::TYPE_QUARTER): ?QuotaPeriod
    {
        return QuotaPeriod::getCurrentPeriod($type);
    }

    /**
     * List quotas with filtering
     */
    public function listQuotas(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Quota::query()->with(['user', 'period']);

        if (!empty($filters['period_id'])) {
            $query->forPeriod($filters['period_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->forUser($filters['user_id']);
        }

        if (!empty($filters['metric_type'])) {
            $query->metricType($filters['metric_type']);
        }

        if (!empty($filters['active_only'])) {
            $query->active();
        }

        return $query->orderByDesc('attainment_percent')->paginate($perPage);
    }

    /**
     * Get user quotas for current period
     */
    public function getUserQuotas(int $userId): Collection
    {
        return Quota::forUser($userId)
            ->active()
            ->with(['period'])
            ->get();
    }

    /**
     * Get team quotas
     */
    public function getTeamQuotas(?int $teamId = null): Collection
    {
        $query = Quota::active()
            ->whereNotNull('team_id')
            ->with(['period']);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->get();
    }

    /**
     * Get quota statistics
     */
    public function getQuotaStats(?int $periodId = null): array
    {
        $query = Quota::query();

        if ($periodId) {
            $query->forPeriod($periodId);
        } else {
            $query->active();
        }

        $quotas = $query->get();

        $achieved = $quotas->filter(fn($q) => $q->is_achieved)->count();

        return [
            'total_quotas' => $quotas->count(),
            'achieved' => $achieved,
            'achievement_rate' => $quotas->count() > 0
                ? round(($achieved / $quotas->count()) * 100, 1)
                : 0,
            'avg_attainment' => round($quotas->avg('attainment_percent') ?? 0, 1),
            'total_target' => round($quotas->sum('target_value'), 2),
            'total_current' => round($quotas->sum('current_value'), 2),
            'by_metric' => $quotas->groupBy('metric_type')
                ->map(fn($group) => [
                    'count' => $group->count(),
                    'avg_attainment' => round($group->avg('attainment_percent'), 1),
                ])
                ->toArray(),
        ];
    }

    // =========================================================================
    // QUOTA COMMAND USE CASES
    // =========================================================================

    /**
     * Create a quota period
     */
    public function createQuotaPeriod(array $data): QuotaPeriod
    {
        // Use factory methods for standard periods
        if (!empty($data['auto_create'])) {
            return match ($data['period_type']) {
                QuotaPeriod::TYPE_MONTH => QuotaPeriod::createMonthPeriod(
                    $data['year'],
                    $data['month']
                ),
                QuotaPeriod::TYPE_QUARTER => QuotaPeriod::createQuarterPeriod(
                    $data['year'],
                    $data['quarter']
                ),
                QuotaPeriod::TYPE_YEAR => QuotaPeriod::createYearPeriod($data['year']),
                default => throw new \InvalidArgumentException('Invalid period type for auto-create'),
            };
        }

        return QuotaPeriod::create([
            'name' => $data['name'],
            'period_type' => $data['period_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update a quota period
     */
    public function updateQuotaPeriod(int $periodId, array $data): QuotaPeriod
    {
        $period = QuotaPeriod::findOrFail($periodId);

        $period->update([
            'name' => $data['name'] ?? $period->name,
            'is_active' => $data['is_active'] ?? $period->is_active,
        ]);

        return $period->fresh();
    }

    /**
     * Create a quota
     */
    public function createQuota(array $data): Quota
    {
        $quota = Quota::create([
            'period_id' => $data['period_id'],
            'user_id' => $data['user_id'] ?? null,
            'team_id' => $data['team_id'] ?? null,
            'metric_type' => $data['metric_type'],
            'metric_field' => $data['metric_field'] ?? null,
            'module_api_name' => $data['module_api_name'] ?? null,
            'target_value' => $data['target_value'],
            'currency' => $data['currency'] ?? null,
            'current_value' => $data['current_value'] ?? 0,
            'attainment_percent' => 0,
            'created_by' => Auth::id(),
        ]);

        $quota->recalculate();

        return $quota->load(['user', 'period']);
    }

    /**
     * Update a quota
     */
    public function updateQuota(int $quotaId, array $data): Quota
    {
        $quota = Quota::findOrFail($quotaId);

        $quota->update([
            'target_value' => $data['target_value'] ?? $quota->target_value,
            'metric_field' => $data['metric_field'] ?? $quota->metric_field,
        ]);

        $quota->recalculate();

        return $quota->fresh(['user', 'period']);
    }

    /**
     * Delete a quota
     */
    public function deleteQuota(int $quotaId): bool
    {
        $quota = Quota::findOrFail($quotaId);

        return DB::transaction(function () use ($quota) {
            $quota->snapshots()->delete();
            return $quota->delete();
        });
    }

    /**
     * Update quota progress
     */
    public function updateQuotaProgress(int $quotaId, float $newValue): Quota
    {
        $quota = Quota::findOrFail($quotaId);
        $quota->updateProgress($newValue);
        return $quota->fresh(['period']);
    }

    /**
     * Add to quota progress
     */
    public function addQuotaProgress(int $quotaId, float $amount): Quota
    {
        $quota = Quota::findOrFail($quotaId);
        $quota->addProgress($amount);
        return $quota->fresh(['period']);
    }

    /**
     * Create snapshot for all active quotas
     */
    public function createQuotaSnapshots(?int $periodId = null): int
    {
        $query = Quota::query();

        if ($periodId) {
            $query->forPeriod($periodId);
        } else {
            $query->active();
        }

        $count = 0;
        $query->each(function (Quota $quota) use (&$count) {
            $quota->createSnapshot();
            $count++;
        });

        return $count;
    }

    /**
     * Bulk create quotas for users
     */
    public function bulkCreateQuotas(int $periodId, array $userQuotas): Collection
    {
        return DB::transaction(function () use ($periodId, $userQuotas) {
            $created = collect();

            foreach ($userQuotas as $userQuota) {
                $quota = Quota::create([
                    'period_id' => $periodId,
                    'user_id' => $userQuota['user_id'],
                    'metric_type' => $userQuota['metric_type'],
                    'metric_field' => $userQuota['metric_field'] ?? null,
                    'target_value' => $userQuota['target_value'],
                    'currency' => $userQuota['currency'] ?? null,
                    'current_value' => 0,
                    'attainment_percent' => 0,
                    'created_by' => Auth::id(),
                ]);

                $created->push($quota);
            }

            return $created;
        });
    }

    // =========================================================================
    // LEADERBOARD USE CASES
    // =========================================================================

    /**
     * Get leaderboard for a period and metric
     */
    public function getLeaderboard(int $periodId, string $metricType, int $limit = 10): Collection
    {
        return LeaderboardEntry::where('period_id', $periodId)
            ->where('metric_type', $metricType)
            ->with(['user'])
            ->orderBy('rank')
            ->limit($limit)
            ->get();
    }

    /**
     * Get user's leaderboard position
     */
    public function getUserLeaderboardPosition(int $userId, int $periodId, string $metricType): ?LeaderboardEntry
    {
        return LeaderboardEntry::where('period_id', $periodId)
            ->where('metric_type', $metricType)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Recalculate leaderboard for a period and metric
     */
    public function recalculateLeaderboard(int $periodId, string $metricType): int
    {
        return DB::transaction(function () use ($periodId, $metricType) {
            // Delete existing entries
            LeaderboardEntry::where('period_id', $periodId)
                ->where('metric_type', $metricType)
                ->delete();

            // Get quotas sorted by attainment
            $quotas = Quota::forPeriod($periodId)
                ->metricType($metricType)
                ->whereNotNull('user_id')
                ->orderByDesc('attainment_percent')
                ->orderByDesc('current_value')
                ->get();

            // Create leaderboard entries
            $rank = 0;
            $prevValue = null;
            $prevAttainment = null;

            foreach ($quotas as $index => $quota) {
                // Handle ties - same rank if same value and attainment
                if ($quota->current_value !== $prevValue || $quota->attainment_percent !== $prevAttainment) {
                    $rank = $index + 1;
                }

                // Calculate trend (compare to last snapshot)
                $lastSnapshot = $quota->snapshots()
                    ->orderByDesc('snapshot_date')
                    ->skip(1)
                    ->first();

                $trend = $lastSnapshot
                    ? $quota->attainment_percent - $lastSnapshot->attainment_percent
                    : 0;

                LeaderboardEntry::create([
                    'period_id' => $periodId,
                    'metric_type' => $metricType,
                    'user_id' => $quota->user_id,
                    'rank' => $rank,
                    'value' => $quota->current_value,
                    'target' => $quota->target_value,
                    'attainment_percent' => $quota->attainment_percent,
                    'trend' => $trend,
                ]);

                $prevValue = $quota->current_value;
                $prevAttainment = $quota->attainment_percent;
            }

            return $quotas->count();
        });
    }

    /**
     * Get all leaderboards for a period
     */
    public function getAllLeaderboards(int $periodId, int $limit = 10): array
    {
        $metricTypes = [
            Quota::METRIC_REVENUE,
            Quota::METRIC_DEALS,
            Quota::METRIC_LEADS,
            Quota::METRIC_CALLS,
            Quota::METRIC_MEETINGS,
        ];

        $leaderboards = [];

        foreach ($metricTypes as $type) {
            $entries = $this->getLeaderboard($periodId, $type, $limit);
            if ($entries->isNotEmpty()) {
                $leaderboards[$type] = $entries;
            }
        }

        return $leaderboards;
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get goal attainment trend
     */
    public function getGoalAttainmentTrend(int $goalId): array
    {
        $logs = GoalProgressLog::where('goal_id', $goalId)
            ->orderBy('log_date')
            ->get();

        $goal = Goal::find($goalId);

        return $logs->map(function ($log) use ($goal) {
            $attainment = $goal->target_value > 0
                ? ($log->value / $goal->target_value) * 100
                : 0;

            return [
                'date' => $log->log_date->format('Y-m-d'),
                'value' => $log->value,
                'attainment' => round($attainment, 1),
            ];
        })->toArray();
    }

    /**
     * Get quota attainment trend
     */
    public function getQuotaAttainmentTrend(int $quotaId): array
    {
        $snapshots = QuotaSnapshot::where('quota_id', $quotaId)
            ->orderBy('snapshot_date')
            ->get();

        return $snapshots->map(fn($snap) => [
            'date' => $snap->snapshot_date->format('Y-m-d'),
            'value' => $snap->current_value,
            'attainment' => $snap->attainment_percent,
        ])->toArray();
    }

    /**
     * Get team performance comparison
     */
    public function getTeamPerformanceComparison(int $periodId): array
    {
        $quotas = Quota::forPeriod($periodId)
            ->whereNotNull('user_id')
            ->with(['user'])
            ->get();

        return $quotas->groupBy('user_id')
            ->map(function ($userQuotas) {
                $user = $userQuotas->first()->user;
                return [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'metrics' => $userQuotas->mapWithKeys(fn($q) => [
                        $q->metric_type => [
                            'target' => $q->target_value,
                            'current' => $q->current_value,
                            'attainment' => $q->attainment_percent,
                        ],
                    ])->toArray(),
                    'avg_attainment' => round($userQuotas->avg('attainment_percent'), 1),
                ];
            })
            ->sortByDesc('avg_attainment')
            ->values()
            ->toArray();
    }

    /**
     * Get historical quota performance
     */
    public function getHistoricalPerformance(int $userId, string $metricType, int $periods = 4): array
    {
        $quotas = Quota::forUser($userId)
            ->metricType($metricType)
            ->with(['period'])
            ->get()
            ->sortByDesc(fn($q) => $q->period->start_date)
            ->take($periods);

        return $quotas->map(fn($quota) => [
            'period_id' => $quota->period_id,
            'period_name' => $quota->period->name,
            'target' => $quota->target_value,
            'achieved' => $quota->current_value,
            'attainment' => $quota->attainment_percent,
            'achieved_target' => $quota->is_achieved,
        ])->values()->toArray();
    }

    /**
     * Check and update overdue goals
     */
    public function processOverdueGoals(): int
    {
        $updated = 0;

        Goal::active()
            ->where('end_date', '<', Carbon::today())
            ->each(function (Goal $goal) use (&$updated) {
                if ($goal->current_value < $goal->target_value) {
                    $goal->markAsMissed();
                    $updated++;
                }
            });

        return $updated;
    }
}
