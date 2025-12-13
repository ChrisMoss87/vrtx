<?php

declare(strict_types=1);

namespace App\Services\Quotas;

use App\Models\Goal;
use App\Models\GoalMilestone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GoalService
{
    /**
     * Create a new goal.
     */
    public function create(array $data, ?int $createdBy = null): Goal
    {
        return DB::transaction(function () use ($data, $createdBy) {
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
                'currency' => $data['currency'] ?? 'USD',
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'current_value' => $data['current_value'] ?? 0,
                'status' => Goal::STATUS_IN_PROGRESS,
                'created_by' => $createdBy,
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

            return $goal->load('milestones');
        });
    }

    /**
     * Update a goal.
     */
    public function update(Goal $goal, array $data): Goal
    {
        return DB::transaction(function () use ($goal, $data) {
            $goal->update([
                'name' => $data['name'] ?? $goal->name,
                'description' => $data['description'] ?? $goal->description,
                'target_value' => $data['target_value'] ?? $goal->target_value,
                'start_date' => $data['start_date'] ?? $goal->start_date,
                'end_date' => $data['end_date'] ?? $goal->end_date,
            ]);

            // Update milestones if provided
            if (isset($data['milestones'])) {
                // Remove existing milestones not in the new list
                $milestoneIds = collect($data['milestones'])
                    ->pluck('id')
                    ->filter()
                    ->toArray();

                $goal->milestones()
                    ->whereNotIn('id', $milestoneIds)
                    ->delete();

                foreach ($data['milestones'] as $index => $milestoneData) {
                    if (!empty($milestoneData['id'])) {
                        $milestone = GoalMilestone::find($milestoneData['id']);
                        if ($milestone) {
                            $milestone->update([
                                'name' => $milestoneData['name'],
                                'target_value' => $milestoneData['target_value'],
                                'target_date' => $milestoneData['target_date'] ?? null,
                                'display_order' => $milestoneData['display_order'] ?? $index,
                            ]);
                        }
                    } else {
                        $goal->milestones()->create([
                            'name' => $milestoneData['name'],
                            'target_value' => $milestoneData['target_value'],
                            'target_date' => $milestoneData['target_date'] ?? null,
                            'display_order' => $milestoneData['display_order'] ?? $index,
                        ]);
                    }
                }
            }

            $goal->recalculate();

            return $goal->fresh(['milestones', 'progressLogs']);
        });
    }

    /**
     * Get goals for a user.
     */
    public function getUserGoals(int $userId, array $filters = []): Collection
    {
        $query = Goal::with(['milestones', 'progressLogs' => function ($q) {
            $q->orderByDesc('log_date')->limit(30);
        }])
            ->forUser($userId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['current'])) {
            $query->current();
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Get active goals (individual, team, company).
     */
    public function getActiveGoals(?int $userId = null): array
    {
        $query = Goal::with(['milestones', 'user'])
            ->active();

        $goals = $query->get();

        return [
            'individual' => $userId
                ? $goals->where('goal_type', Goal::TYPE_INDIVIDUAL)->where('user_id', $userId)->values()
                : collect(),
            'team' => $goals->where('goal_type', Goal::TYPE_TEAM)->values(),
            'company' => $goals->where('goal_type', Goal::TYPE_COMPANY)->values(),
        ];
    }

    /**
     * Get goal progress details.
     */
    public function getGoalProgress(Goal $goal): array
    {
        $goal->load(['milestones', 'progressLogs', 'user']);

        $achievedMilestones = $goal->milestones->where('is_achieved', true)->count();
        $totalMilestones = $goal->milestones->count();

        // Calculate trend (last 7 days)
        $recentLogs = $goal->progressLogs->take(7);
        $trend = 0;
        if ($recentLogs->count() >= 2) {
            $oldest = $recentLogs->last()->value;
            $newest = $recentLogs->first()->value;
            $trend = $newest - $oldest;
        }

        // Projected completion
        $daysElapsed = $goal->start_date->diffInDays(Carbon::today());
        $projectedValue = null;
        if ($daysElapsed > 0 && $goal->current_value > 0) {
            $dailyRate = $goal->current_value / $daysElapsed;
            $daysTotal = $goal->start_date->diffInDays($goal->end_date);
            $projectedValue = round($dailyRate * $daysTotal, 2);
        }

        return [
            'goal' => $goal,
            'milestones' => [
                'total' => $totalMilestones,
                'achieved' => $achievedMilestones,
                'next' => $goal->next_milestone,
            ],
            'trend' => [
                'change' => $trend,
                'direction' => $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'flat'),
                'logs' => $recentLogs->map(fn($l) => [
                    'date' => $l->log_date->format('M d'),
                    'value' => $l->value,
                    'change' => $l->change_amount,
                ])->reverse()->values(),
            ],
            'projection' => [
                'projected_value' => $projectedValue,
                'on_track' => $projectedValue && $projectedValue >= $goal->target_value,
                'pace_required' => $goal->days_remaining > 0
                    ? round($goal->gap_to_target / $goal->days_remaining, 2)
                    : null,
            ],
        ];
    }

    /**
     * Update goal progress from external source.
     */
    public function updateGoalProgress(Goal $goal, float $newValue, ?string $source = null): void
    {
        $goal->updateProgress($newValue, $source);
    }

    /**
     * Check and update overdue goals.
     */
    public function processOverdueGoals(): int
    {
        $count = 0;

        Goal::where('status', Goal::STATUS_IN_PROGRESS)
            ->where('end_date', '<', Carbon::today())
            ->whereColumn('current_value', '<', 'target_value')
            ->chunk(100, function ($goals) use (&$count) {
                foreach ($goals as $goal) {
                    $goal->markAsMissed();
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Get goal statistics.
     */
    public function getStats(?int $userId = null): array
    {
        $query = Goal::query();

        if ($userId) {
            $query->forUser($userId);
        }

        $goals = $query->get();

        return [
            'total' => $goals->count(),
            'in_progress' => $goals->where('status', Goal::STATUS_IN_PROGRESS)->count(),
            'achieved' => $goals->where('status', Goal::STATUS_ACHIEVED)->count(),
            'missed' => $goals->where('status', Goal::STATUS_MISSED)->count(),
            'achievement_rate' => $goals->count() > 0
                ? round(($goals->where('status', Goal::STATUS_ACHIEVED)->count() / $goals->count()) * 100, 1)
                : 0,
            'by_type' => [
                'individual' => $goals->where('goal_type', Goal::TYPE_INDIVIDUAL)->count(),
                'team' => $goals->where('goal_type', Goal::TYPE_TEAM)->count(),
                'company' => $goals->where('goal_type', Goal::TYPE_COMPANY)->count(),
            ],
        ];
    }
}
