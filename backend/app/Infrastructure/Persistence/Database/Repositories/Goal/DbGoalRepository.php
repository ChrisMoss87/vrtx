<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Goal;

use App\Domain\Goal\Entities\Goal as GoalEntity;
use App\Domain\Goal\Repositories\GoalRepositoryInterface;
use App\Domain\Goal\ValueObjects\GoalStatus;
use App\Domain\Goal\ValueObjects\GoalType;
use App\Domain\Goal\ValueObjects\MetricType;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbGoalRepository implements GoalRepositoryInterface
{
    private const TABLE_GOALS = 'goals';
    private const TABLE_GOAL_MILESTONES = 'goal_milestones';
    private const TABLE_GOAL_PROGRESS_LOGS = 'goal_progress_logs';
    private const TABLE_QUOTAS = 'quotas';
    private const TABLE_QUOTA_PERIODS = 'quota_periods';
    private const TABLE_QUOTA_SNAPSHOTS = 'quota_snapshots';
    private const TABLE_LEADERBOARD_ENTRIES = 'leaderboard_entries';
    private const TABLE_USERS = 'users';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?GoalEntity
    {
        $row = DB::table(self::TABLE_GOALS)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(GoalEntity $entity): GoalEntity
    {
        $data = $this->toRowData($entity);

        if ($entity->getId() !== null) {
            DB::table(self::TABLE_GOALS)
                ->where('id', $entity->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $entity->getId();
        } else {
            $id = DB::table(self::TABLE_GOALS)->insertGetId(
                array_merge($data, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE_GOALS)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array
    {
        $goal = DB::table(self::TABLE_GOALS)->where('id', $id)->first();

        if (!$goal) {
            return null;
        }

        $goalArray = (array) $goal;

        // Load user
        if ($goal->user_id) {
            $user = DB::table(self::TABLE_USERS)->where('id', $goal->user_id)->first();
            $goalArray['user'] = $user ? (array) $user : null;
        }

        // Load createdBy
        if ($goal->created_by) {
            $createdBy = DB::table(self::TABLE_USERS)->where('id', $goal->created_by)->first();
            $goalArray['created_by'] = $createdBy ? (array) $createdBy : null;
        }

        // Load milestones
        $milestones = DB::table(self::TABLE_GOAL_MILESTONES)
            ->where('goal_id', $id)
            ->orderBy('display_order')
            ->get();
        $goalArray['milestones'] = $milestones->map(fn($m) => (array) $m)->toArray();

        // Load progress logs (last 30)
        $progressLogs = DB::table(self::TABLE_GOAL_PROGRESS_LOGS)
            ->where('goal_id', $id)
            ->orderByDesc('log_date')
            ->limit(30)
            ->get();
        $goalArray['progress_logs'] = $progressLogs->map(fn($p) => (array) $p)->toArray();

        return $goalArray;
    }

    public function findAll(): array
    {
        $goals = DB::table(self::TABLE_GOALS)->get();
        return $goals->map(fn($g) => (array) $g)->toArray();
    }

    // =========================================================================
    // GOAL QUERY USE CASES
    // =========================================================================

    public function listGoals(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_GOALS);

        // Filter by type (individual, team, company)
        if (!empty($filters['goal_type'])) {
            $query->where('goal_type', $filters['goal_type']);
        }

        // Filter by user
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter for active only (status = in_progress)
        if (!empty($filters['active_only'])) {
            $query->where('status', 'in_progress');
        }

        // Filter for current period only
        if (!empty($filters['current_only'])) {
            $today = Carbon::today()->format('Y-m-d');
            $query->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today);
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

        // Get total count
        $total = $query->count();

        // Apply pagination
        $offset = ($page - 1) * $perPage;
        $items = $query->offset($offset)->limit($perPage)->get();

        // Enrich each item with relationships
        $enrichedItems = $items->map(function ($goal) {
            $goalArray = (array) $goal;

            // Load user
            if ($goal->user_id) {
                $user = DB::table(self::TABLE_USERS)->where('id', $goal->user_id)->first();
                $goalArray['user'] = $user ? (array) $user : null;
            }

            // Load milestones
            $milestones = DB::table(self::TABLE_GOAL_MILESTONES)
                ->where('goal_id', $goal->id)
                ->orderBy('display_order')
                ->get();
            $goalArray['milestones'] = $milestones->map(fn($m) => (array) $m)->toArray();

            // Load progress logs
            $progressLogs = DB::table(self::TABLE_GOAL_PROGRESS_LOGS)
                ->where('goal_id', $goal->id)
                ->orderByDesc('log_date')
                ->get();
            $goalArray['progress_logs'] = $progressLogs->map(fn($p) => (array) $p)->toArray();

            return $goalArray;
        })->toArray();

        return PaginatedResult::create(
            items: $enrichedItems,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getUserGoals(int $userId, bool $currentOnly = true): array
    {
        $query = DB::table(self::TABLE_GOALS)
            ->where('user_id', $userId)
            ->where('status', 'in_progress');

        if ($currentOnly) {
            $today = Carbon::today()->format('Y-m-d');
            $query->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today);
        }

        $goals = $query->orderBy('end_date')->get();

        return $goals->map(function ($goal) {
            $goalArray = (array) $goal;

            // Load milestones
            $milestones = DB::table(self::TABLE_GOAL_MILESTONES)
                ->where('goal_id', $goal->id)
                ->orderBy('display_order')
                ->get();
            $goalArray['milestones'] = $milestones->map(fn($m) => (array) $m)->toArray();

            return $goalArray;
        })->toArray();
    }

    public function getTeamGoals(?int $teamId = null): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $query = DB::table(self::TABLE_GOALS)
            ->where('goal_type', 'team')
            ->where('status', 'in_progress')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        $goals = $query->orderBy('end_date')->get();

        return $goals->map(function ($goal) {
            $goalArray = (array) $goal;

            // Load milestones
            $milestones = DB::table(self::TABLE_GOAL_MILESTONES)
                ->where('goal_id', $goal->id)
                ->orderBy('display_order')
                ->get();
            $goalArray['milestones'] = $milestones->map(fn($m) => (array) $m)->toArray();

            return $goalArray;
        })->toArray();
    }

    public function getCompanyGoals(): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $goals = DB::table(self::TABLE_GOALS)
            ->where('goal_type', 'company')
            ->where('status', 'in_progress')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->orderBy('end_date')
            ->get();

        return $goals->map(function ($goal) {
            $goalArray = (array) $goal;

            // Load milestones
            $milestones = DB::table(self::TABLE_GOAL_MILESTONES)
                ->where('goal_id', $goal->id)
                ->orderBy('display_order')
                ->get();
            $goalArray['milestones'] = $milestones->map(fn($m) => (array) $m)->toArray();

            return $goalArray;
        })->toArray();
    }

    public function getGoalProgressHistory(int $goalId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = DB::table(self::TABLE_GOAL_PROGRESS_LOGS)
            ->where('goal_id', $goalId)
            ->orderBy('log_date');

        if ($startDate) {
            $query->where('log_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('log_date', '<=', $endDate);
        }

        $logs = $query->get();
        return $logs->map(fn($log) => (array) $log)->toArray();
    }

    public function getAtRiskGoals(): array
    {
        $today = Carbon::today();
        $todayStr = $today->format('Y-m-d');

        $goals = DB::table(self::TABLE_GOALS)
            ->where('status', 'in_progress')
            ->where('start_date', '<=', $todayStr)
            ->where('end_date', '>=', $todayStr)
            ->get();

        $atRiskGoals = $goals->filter(function ($goal) use ($today) {
            // Calculate expected progress based on time elapsed
            $startDate = Carbon::parse($goal->start_date);
            $endDate = Carbon::parse($goal->end_date);
            $totalDays = $startDate->diffInDays($endDate) + 1;
            $daysElapsed = $startDate->diffInDays($today) + 1;
            $expectedPercent = ($daysElapsed / $totalDays) * 100;

            // Calculate progress percent
            $progressPercent = $goal->target_value > 0
                ? ($goal->current_value / $goal->target_value) * 100
                : 0;

            // Goal is at risk if actual progress is less than 75% of expected
            return $progressPercent < ($expectedPercent * 0.75);
        });

        return $atRiskGoals->map(function ($goal) {
            $goalArray = (array) $goal;

            // Load user
            if ($goal->user_id) {
                $user = DB::table(self::TABLE_USERS)->where('id', $goal->user_id)->first();
                $goalArray['user'] = $user ? (array) $user : null;
            }

            return $goalArray;
        })->values()->toArray();
    }

    public function getOverdueGoals(): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $goals = DB::table(self::TABLE_GOALS)
            ->where('status', 'in_progress')
            ->where('end_date', '<', $today)
            ->orderBy('end_date')
            ->get();

        return $goals->map(function ($goal) {
            $goalArray = (array) $goal;

            // Load user
            if ($goal->user_id) {
                $user = DB::table(self::TABLE_USERS)->where('id', $goal->user_id)->first();
                $goalArray['user'] = $user ? (array) $user : null;
            }

            return $goalArray;
        })->toArray();
    }

    public function getGoalStats(?int $userId = null): array
    {
        $today = Carbon::today();
        $todayStr = $today->format('Y-m-d');
        $quarterStart = Carbon::now()->startOfQuarter()->format('Y-m-d H:i:s');

        // Build base query
        $baseQuery = DB::table(self::TABLE_GOALS);
        if ($userId) {
            $baseQuery->where('user_id', $userId);
        }

        // Get current goals
        $currentGoalsQuery = clone $baseQuery;
        $currentGoals = $currentGoalsQuery
            ->where('start_date', '<=', $todayStr)
            ->where('end_date', '>=', $todayStr)
            ->get();

        // Count achieved this quarter
        $achievedThisPeriodQuery = clone $baseQuery;
        $achievedThisPeriod = $achievedThisPeriodQuery
            ->where('status', 'achieved')
            ->whereNotNull('achieved_at')
            ->where('achieved_at', '>=', $quarterStart)
            ->count();

        // Count missed this quarter
        $missedThisPeriodQuery = clone $baseQuery;
        $missedThisPeriod = $missedThisPeriodQuery
            ->where('status', 'missed')
            ->where('end_date', '>=', $quarterStart)
            ->count();

        // Calculate on track count
        $onTrackCount = $currentGoals->filter(function ($goal) use ($today) {
            $startDate = Carbon::parse($goal->start_date);
            $endDate = Carbon::parse($goal->end_date);
            $totalDays = $startDate->diffInDays($endDate) + 1;
            $daysElapsed = $startDate->diffInDays($today) + 1;
            $expectedPercent = ($daysElapsed / $totalDays) * 100;

            $progressPercent = $goal->target_value > 0
                ? ($goal->current_value / $goal->target_value) * 100
                : 0;

            return $progressPercent >= ($expectedPercent * 0.9);
        })->count();

        // Count by type
        $individualQuery = clone $baseQuery;
        $individualCount = $individualQuery
            ->where('goal_type', 'individual')
            ->where('start_date', '<=', $todayStr)
            ->where('end_date', '>=', $todayStr)
            ->count();

        $teamQuery = clone $baseQuery;
        $teamCount = $teamQuery
            ->where('goal_type', 'team')
            ->where('start_date', '<=', $todayStr)
            ->where('end_date', '>=', $todayStr)
            ->count();

        $companyQuery = clone $baseQuery;
        $companyCount = $companyQuery
            ->where('goal_type', 'company')
            ->where('start_date', '<=', $todayStr)
            ->where('end_date', '>=', $todayStr)
            ->count();

        return [
            'total_active' => $currentGoals->where('status', 'in_progress')->count(),
            'achieved_this_quarter' => $achievedThisPeriod,
            'missed_this_quarter' => $missedThisPeriod,
            'on_track' => $onTrackCount,
            'at_risk' => $currentGoals->count() - $onTrackCount,
            'avg_attainment' => round($currentGoals->avg('attainment_percent') ?? 0, 1),
            'by_type' => [
                'individual' => $individualCount,
                'team' => $teamCount,
                'company' => $companyCount,
            ],
        ];
    }

    // =========================================================================
    // GOAL COMMAND USE CASES
    // =========================================================================

    public function createGoal(array $data, int $createdBy): array
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $goalId = DB::table(self::TABLE_GOALS)->insertGetId([
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
                'status' => 'in_progress',
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create milestones if provided
            if (!empty($data['milestones'])) {
                foreach ($data['milestones'] as $index => $milestone) {
                    DB::table(self::TABLE_GOAL_MILESTONES)->insert([
                        'goal_id' => $goalId,
                        'name' => $milestone['name'],
                        'target_value' => $milestone['target_value'],
                        'target_date' => $milestone['target_date'] ?? null,
                        'display_order' => $milestone['display_order'] ?? $index,
                        'is_achieved' => false,
                        'achieved_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Recalculate attainment
            $this->recalculateGoalAttainment($goalId);

            return $this->findByIdAsArray($goalId);
        });
    }

    public function updateGoal(int $goalId, array $data): array
    {
        $goal = DB::table(self::TABLE_GOALS)->where('id', $goalId)->first();
        if (!$goal) {
            throw new \RuntimeException("Goal not found with ID: {$goalId}");
        }

        DB::table(self::TABLE_GOALS)->where('id', $goalId)->update([
            'name' => $data['name'] ?? $goal->name,
            'description' => $data['description'] ?? $goal->description,
            'target_value' => $data['target_value'] ?? $goal->target_value,
            'end_date' => $data['end_date'] ?? $goal->end_date,
            'updated_at' => now(),
        ]);

        // Recalculate attainment
        $this->recalculateGoalAttainment($goalId);

        return $this->findByIdAsArray($goalId);
    }

    public function deleteGoal(int $goalId): bool
    {
        return DB::transaction(function () use ($goalId) {
            DB::table(self::TABLE_GOAL_MILESTONES)->where('goal_id', $goalId)->delete();
            DB::table(self::TABLE_GOAL_PROGRESS_LOGS)->where('goal_id', $goalId)->delete();
            return DB::table(self::TABLE_GOALS)->where('id', $goalId)->delete() > 0;
        });
    }

    public function updateGoalProgress(int $goalId, float $newValue, ?string $source = null, ?int $sourceRecordId = null): array
    {
        $goal = DB::table(self::TABLE_GOALS)->where('id', $goalId)->first();
        if (!$goal) {
            throw new \RuntimeException("Goal not found with ID: {$goalId}");
        }

        return DB::transaction(function () use ($goalId, $goal, $newValue, $source, $sourceRecordId) {
            $changeAmount = $newValue - $goal->current_value;

            // Update goal progress
            DB::table(self::TABLE_GOALS)->where('id', $goalId)->update([
                'current_value' => $newValue,
                'updated_at' => now(),
            ]);

            // Create progress log if there's a change
            if ($changeAmount != 0) {
                DB::table(self::TABLE_GOAL_PROGRESS_LOGS)->insert([
                    'goal_id' => $goalId,
                    'log_date' => Carbon::today(),
                    'value' => $newValue,
                    'change_amount' => $changeAmount,
                    'change_source' => $source,
                    'source_record_id' => $sourceRecordId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Recalculate and check for achievement
            $this->recalculateGoalAttainment($goalId);
            $this->updateMilestoneAchievements($goalId, $newValue);
            $this->checkAndMarkGoalAsAchieved($goalId);

            return $this->findByIdAsArray($goalId);
        });
    }

    public function addGoalProgress(int $goalId, float $amount, ?string $source = null, ?int $sourceRecordId = null): array
    {
        $goal = DB::table(self::TABLE_GOALS)->where('id', $goalId)->first();
        if (!$goal) {
            throw new \RuntimeException("Goal not found with ID: {$goalId}");
        }

        return $this->updateGoalProgress($goalId, $goal->current_value + $amount, $source, $sourceRecordId);
    }

    public function pauseGoal(int $goalId): array
    {
        DB::table(self::TABLE_GOALS)->where('id', $goalId)->update([
            'status' => 'paused',
            'updated_at' => now(),
        ]);

        $goal = DB::table(self::TABLE_GOALS)->where('id', $goalId)->first();
        return (array) $goal;
    }

    public function resumeGoal(int $goalId): array
    {
        DB::table(self::TABLE_GOALS)->where('id', $goalId)->update([
            'status' => 'in_progress',
            'updated_at' => now(),
        ]);

        $goal = DB::table(self::TABLE_GOALS)->where('id', $goalId)->first();
        return (array) $goal;
    }

    public function markGoalAsMissed(int $goalId): array
    {
        DB::table(self::TABLE_GOALS)->where('id', $goalId)->update([
            'status' => 'missed',
            'updated_at' => now(),
        ]);

        $goal = DB::table(self::TABLE_GOALS)->where('id', $goalId)->first();
        return (array) $goal;
    }

    public function addMilestone(int $goalId, array $data): array
    {
        $maxOrder = DB::table(self::TABLE_GOAL_MILESTONES)
            ->where('goal_id', $goalId)
            ->max('display_order') ?? 0;

        $milestoneId = DB::table(self::TABLE_GOAL_MILESTONES)->insertGetId([
            'goal_id' => $goalId,
            'name' => $data['name'],
            'target_value' => $data['target_value'],
            'target_date' => $data['target_date'] ?? null,
            'display_order' => $data['display_order'] ?? $maxOrder + 1,
            'is_achieved' => false,
            'achieved_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $milestone = DB::table(self::TABLE_GOAL_MILESTONES)->where('id', $milestoneId)->first();
        return (array) $milestone;
    }

    public function updateMilestone(int $milestoneId, array $data): array
    {
        $milestone = DB::table(self::TABLE_GOAL_MILESTONES)->where('id', $milestoneId)->first();
        if (!$milestone) {
            throw new \RuntimeException("Milestone not found with ID: {$milestoneId}");
        }

        DB::table(self::TABLE_GOAL_MILESTONES)->where('id', $milestoneId)->update([
            'name' => $data['name'] ?? $milestone->name,
            'target_value' => $data['target_value'] ?? $milestone->target_value,
            'target_date' => $data['target_date'] ?? $milestone->target_date,
            'updated_at' => now(),
        ]);

        $updated = DB::table(self::TABLE_GOAL_MILESTONES)->where('id', $milestoneId)->first();
        return (array) $updated;
    }

    public function deleteMilestone(int $milestoneId): bool
    {
        return DB::table(self::TABLE_GOAL_MILESTONES)->where('id', $milestoneId)->delete() > 0;
    }

    // =========================================================================
    // QUOTA QUERY USE CASES
    // =========================================================================

    public function listQuotaPeriods(array $filters = []): array
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

        return $query->orderByDesc('start_date')->get()->toArray();
    }

    public function getQuotaPeriod(int $periodId): ?array
    {
        $period = QuotaPeriod::with([
            'quotas' => fn($q) => $q->with(['user', 'snapshots']),
        ])->find($periodId);

        return $period ? $period->toArray() : null;
    }

    public function getCurrentPeriod(string $type = 'quarter'): ?array
    {
        $period = QuotaPeriod::getCurrentPeriod($type);
        return $period ? $period->toArray() : null;
    }

    public function listQuotas(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
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

        $paginator = $query->orderByDesc('attainment_percent')->paginate($perPage, ['*'], 'page', $page);

        return PaginatedResult::create(
            items: $paginator->items() ? array_map(fn($item) => $item->toArray(), $paginator->items()) : [],
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage()
        );
    }

    public function getUserQuotas(int $userId): array
    {
        return Quota::forUser($userId)
            ->active()
            ->with(['period'])
            ->get()
            ->toArray();
    }

    public function getTeamQuotas(?int $teamId = null): array
    {
        $query = Quota::active()
            ->whereNotNull('team_id')
            ->with(['period']);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->get()->toArray();
    }

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

    public function createQuotaPeriod(array $data): array
    {
        // Use factory methods for standard periods
        if (!empty($data['auto_create'])) {
            $period = match ($data['period_type']) {
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
            return $period->toArray();
        }

        $period = QuotaPeriod::create([
            'name' => $data['name'],
            'period_type' => $data['period_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $period->toArray();
    }

    public function updateQuotaPeriod(int $periodId, array $data): array
    {
        $period = QuotaPeriod::findOrFail($periodId);

        $period->update([
            'name' => $data['name'] ?? $period->name,
            'is_active' => $data['is_active'] ?? $period->is_active,
        ]);

        return $period->fresh()->toArray();
    }

    public function createQuota(array $data, int $createdBy): array
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
            'created_by' => $createdBy,
        ]);

        $quota->recalculate();

        return $quota->load(['user', 'period'])->toArray();
    }

    public function updateQuota(int $quotaId, array $data): array
    {
        $quota = Quota::findOrFail($quotaId);

        $quota->update([
            'target_value' => $data['target_value'] ?? $quota->target_value,
            'metric_field' => $data['metric_field'] ?? $quota->metric_field,
        ]);

        $quota->recalculate();

        return $quota->fresh(['user', 'period'])->toArray();
    }

    public function deleteQuota(int $quotaId): bool
    {
        $quota = Quota::findOrFail($quotaId);

        return DB::transaction(function () use ($quota) {
            $quota->snapshots()->delete();
            return $quota->delete();
        });
    }

    public function updateQuotaProgress(int $quotaId, float $newValue): array
    {
        $quota = Quota::findOrFail($quotaId);
        $quota->updateProgress($newValue);
        return $quota->fresh(['period'])->toArray();
    }

    public function addQuotaProgress(int $quotaId, float $amount): array
    {
        $quota = Quota::findOrFail($quotaId);
        $quota->addProgress($amount);
        return $quota->fresh(['period'])->toArray();
    }

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

    public function bulkCreateQuotas(int $periodId, array $userQuotas, int $createdBy): array
    {
        return DB::transaction(function () use ($periodId, $userQuotas, $createdBy) {
            $created = [];

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
                    'created_by' => $createdBy,
                ]);

                $created[] = $quota->toArray();
            }

            return $created;
        });
    }

    // =========================================================================
    // LEADERBOARD USE CASES
    // =========================================================================

    public function getLeaderboard(int $periodId, string $metricType, int $limit = 10): array
    {
        return LeaderboardEntry::where('period_id', $periodId)
            ->where('metric_type', $metricType)
            ->with(['user'])
            ->orderBy('rank')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getUserLeaderboardPosition(int $userId, int $periodId, string $metricType): ?array
    {
        $entry = LeaderboardEntry::where('period_id', $periodId)
            ->where('metric_type', $metricType)
            ->where('user_id', $userId)
            ->first();

        return $entry ? $entry->toArray() : null;
    }

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
            if (!empty($entries)) {
                $leaderboards[$type] = $entries;
            }
        }

        return $leaderboards;
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    public function getGoalAttainmentTrend(int $goalId): array
    {
        $logs = DB::table(self::TABLE_GOAL_PROGRESS_LOGS)
            ->where('goal_id', $goalId)
            ->orderBy('log_date')
            ->get();

        $goal = DB::table(self::TABLE_GOALS)->where('id', $goalId)->first();

        if (!$goal) {
            return [];
        }

        return $logs->map(function ($log) use ($goal) {
            $attainment = $goal->target_value > 0
                ? ($log->value / $goal->target_value) * 100
                : 0;

            return [
                'date' => Carbon::parse($log->log_date)->format('Y-m-d'),
                'value' => $log->value,
                'attainment' => round($attainment, 1),
            ];
        })->toArray();
    }

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

    public function processOverdueGoals(): int
    {
        $today = Carbon::today()->format('Y-m-d');

        $overdueGoals = DB::table(self::TABLE_GOALS)
            ->where('status', 'in_progress')
            ->where('end_date', '<', $today)
            ->get();

        $updated = 0;

        foreach ($overdueGoals as $goal) {
            if ($goal->current_value < $goal->target_value) {
                DB::table(self::TABLE_GOALS)->where('id', $goal->id)->update([
                    'status' => 'missed',
                    'updated_at' => now(),
                ]);
                $updated++;
            }
        }

        return $updated;
    }

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): GoalEntity
    {
        return GoalEntity::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            description: $row->description,
            goalType: GoalType::from($row->goal_type),
            userId: $row->user_id ? (int) $row->user_id : null,
            teamId: $row->team_id ? (int) $row->team_id : null,
            metricType: MetricType::from($row->metric_type),
            metricField: $row->metric_field,
            moduleApiName: $row->module_api_name,
            targetValue: (float) $row->target_value,
            currency: $row->currency,
            startDate: new DateTimeImmutable($row->start_date),
            endDate: new DateTimeImmutable($row->end_date),
            currentValue: (float) $row->current_value,
            attainmentPercent: (float) $row->attainment_percent,
            status: GoalStatus::from($row->status),
            achievedAt: $row->achieved_at ? new DateTimeImmutable($row->achieved_at) : null,
            createdBy: (int) $row->created_by,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(GoalEntity $goal): array
    {
        return [
            'name' => $goal->getName(),
            'description' => $goal->getDescription(),
            'goal_type' => $goal->getGoalType()->value,
            'user_id' => $goal->getUserId(),
            'team_id' => $goal->getTeamId(),
            'metric_type' => $goal->getMetricType()->value,
            'metric_field' => $goal->getMetricField(),
            'module_api_name' => $goal->getModuleApiName(),
            'target_value' => $goal->getTargetValue(),
            'currency' => $goal->getCurrency(),
            'start_date' => $goal->getStartDate()->format('Y-m-d'),
            'end_date' => $goal->getEndDate()->format('Y-m-d'),
            'current_value' => $goal->getCurrentValue(),
            'attainment_percent' => $goal->getAttainmentPercent(),
            'status' => $goal->getStatus()->value,
            'achieved_at' => $goal->getAchievedAt()?->format('Y-m-d H:i:s'),
            'created_by' => $goal->getCreatedBy(),
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function recalculateGoalAttainment(int $goalId): void
    {
        $goal = DB::table(self::TABLE_GOALS)->where('id', $goalId)->first();
        if (!$goal) {
            return;
        }

        $attainmentPercent = $goal->target_value > 0
            ? round(($goal->current_value / $goal->target_value) * 100, 2)
            : 0;

        DB::table(self::TABLE_GOALS)->where('id', $goalId)->update([
            'attainment_percent' => $attainmentPercent,
        ]);
    }

    private function updateMilestoneAchievements(int $goalId, float $currentValue): void
    {
        $milestones = DB::table(self::TABLE_GOAL_MILESTONES)
            ->where('goal_id', $goalId)
            ->where('is_achieved', false)
            ->where('target_value', '<=', $currentValue)
            ->get();

        foreach ($milestones as $milestone) {
            DB::table(self::TABLE_GOAL_MILESTONES)
                ->where('id', $milestone->id)
                ->update([
                    'is_achieved' => true,
                    'achieved_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    private function checkAndMarkGoalAsAchieved(int $goalId): void
    {
        $goal = DB::table(self::TABLE_GOALS)->where('id', $goalId)->first();
        if (!$goal) {
            return;
        }

        if ($goal->status === 'in_progress' && $goal->current_value >= $goal->target_value) {
            DB::table(self::TABLE_GOALS)->where('id', $goalId)->update([
                'status' => 'achieved',
                'achieved_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
