<?php

declare(strict_types=1);

namespace App\Application\Services\Playbook;

use App\Models\Playbook;
use App\Models\PlaybookActivity;
use App\Models\PlaybookGoal;
use App\Models\PlaybookGoalResult;
use App\Models\PlaybookInstance;
use App\Models\PlaybookPhase;
use App\Models\PlaybookTask;
use App\Models\PlaybookTaskInstance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlaybookApplicationService
{
    // =========================================================================
    // QUERY USE CASES - PLAYBOOKS
    // =========================================================================

    /**
     * List playbooks with filtering and pagination.
     */
    public function listPlaybooks(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Playbook::query()
            ->with(['creator:id,name,email', 'defaultOwner:id,name,email']);

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by trigger module
        if (!empty($filters['trigger_module'])) {
            $query->forModule($filters['trigger_module']);
        }

        // Filter by creator
        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'display_order';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a single playbook by ID.
     */
    public function getPlaybook(int $id): ?Playbook
    {
        return Playbook::with([
            'phases.tasks',
            'tasks',
            'goals',
            'creator',
            'defaultOwner'
        ])->find($id);
    }

    /**
     * Get active playbooks for a module.
     */
    public function getActivePlaybooksForModule(string $module): Collection
    {
        return Playbook::active()
            ->forModule($module)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Get playbook by slug.
     */
    public function getPlaybookBySlug(string $slug): ?Playbook
    {
        return Playbook::with(['phases.tasks', 'goals'])
            ->where('slug', $slug)
            ->first();
    }

    // =========================================================================
    // QUERY USE CASES - PHASES & TASKS
    // =========================================================================

    /**
     * Get phases for a playbook.
     */
    public function getPhases(int $playbookId): Collection
    {
        return PlaybookPhase::where('playbook_id', $playbookId)
            ->with('tasks')
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Get tasks for a playbook.
     */
    public function getTasks(int $playbookId): Collection
    {
        return PlaybookTask::where('playbook_id', $playbookId)
            ->with(['phase', 'assignee'])
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Get a specific task.
     */
    public function getTask(int $taskId): ?PlaybookTask
    {
        return PlaybookTask::with(['playbook', 'phase', 'assignee'])->find($taskId);
    }

    // =========================================================================
    // QUERY USE CASES - INSTANCES
    // =========================================================================

    /**
     * List playbook instances with filtering and pagination.
     */
    public function listInstances(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = PlaybookInstance::query()
            ->with(['playbook:id,name,slug', 'owner:id,name,email']);

        // Filter by playbook
        if (!empty($filters['playbook_id'])) {
            $query->where('playbook_id', $filters['playbook_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by owner
        if (!empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        // Filter by related record
        if (!empty($filters['related_module']) && !empty($filters['related_id'])) {
            $query->forRecord($filters['related_module'], $filters['related_id']);
        }

        // Filter by date range
        if (!empty($filters['started_from'])) {
            $query->where('started_at', '>=', $filters['started_from']);
        }
        if (!empty($filters['started_to'])) {
            $query->where('started_at', '<=', $filters['started_to']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a single playbook instance.
     */
    public function getInstance(int $id): ?PlaybookInstance
    {
        return PlaybookInstance::with([
            'playbook',
            'owner',
            'taskInstances.task',
            'taskInstances.assignee',
            'activities.user',
            'goalResults.goal'
        ])->find($id);
    }

    /**
     * Get instances for a specific record.
     */
    public function getInstancesForRecord(string $module, int $recordId): Collection
    {
        return PlaybookInstance::forRecord($module, $recordId)
            ->with(['playbook', 'owner'])
            ->orderBy('started_at', 'desc')
            ->get();
    }

    /**
     * Get active instances for a user.
     */
    public function getActiveInstancesForUser(int $userId): Collection
    {
        return PlaybookInstance::active()
            ->where('owner_id', $userId)
            ->with(['playbook'])
            ->orderBy('target_completion_at')
            ->get();
    }

    // =========================================================================
    // QUERY USE CASES - TASK INSTANCES
    // =========================================================================

    /**
     * Get task instances for an instance.
     */
    public function getTaskInstances(int $instanceId): Collection
    {
        return PlaybookTaskInstance::where('instance_id', $instanceId)
            ->with(['task.phase', 'assignee'])
            ->orderBy('due_at')
            ->get();
    }

    /**
     * Get pending task instances for a user.
     */
    public function getPendingTasksForUser(int $userId): Collection
    {
        return PlaybookTaskInstance::pending()
            ->where('assigned_to', $userId)
            ->with(['instance.playbook', 'task'])
            ->orderBy('due_at')
            ->get();
    }

    /**
     * Get overdue task instances for a user.
     */
    public function getOverdueTasksForUser(int $userId): Collection
    {
        return PlaybookTaskInstance::overdue()
            ->where('assigned_to', $userId)
            ->with(['instance.playbook', 'task'])
            ->orderBy('due_at')
            ->get();
    }

    // =========================================================================
    // COMMAND USE CASES - PLAYBOOKS
    // =========================================================================

    /**
     * Create a new playbook.
     */
    public function createPlaybook(array $data): Playbook
    {
        return DB::transaction(function () use ($data) {
            $playbook = Playbook::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? null,
                'description' => $data['description'] ?? null,
                'trigger_module' => $data['trigger_module'],
                'trigger_condition' => $data['trigger_condition'] ?? null,
                'trigger_config' => $data['trigger_config'] ?? [],
                'estimated_days' => $data['estimated_days'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'auto_assign' => $data['auto_assign'] ?? false,
                'default_owner_id' => $data['default_owner_id'] ?? null,
                'tags' => $data['tags'] ?? [],
                'display_order' => $data['display_order'] ?? 0,
                'created_by' => Auth::id(),
            ]);

            // Create phases if provided
            if (!empty($data['phases'])) {
                foreach ($data['phases'] as $index => $phaseData) {
                    $this->createPhase($playbook->id, array_merge($phaseData, [
                        'display_order' => $index,
                    ]));
                }
            }

            return $playbook;
        });
    }

    /**
     * Update a playbook.
     */
    public function updatePlaybook(int $id, array $data): Playbook
    {
        $playbook = Playbook::findOrFail($id);

        $playbook->update(array_filter([
            'name' => $data['name'] ?? null,
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
            'trigger_module' => $data['trigger_module'] ?? null,
            'trigger_condition' => $data['trigger_condition'] ?? null,
            'trigger_config' => $data['trigger_config'] ?? null,
            'estimated_days' => $data['estimated_days'] ?? null,
            'is_active' => $data['is_active'] ?? null,
            'auto_assign' => $data['auto_assign'] ?? null,
            'default_owner_id' => $data['default_owner_id'] ?? null,
            'tags' => $data['tags'] ?? null,
            'display_order' => $data['display_order'] ?? null,
        ], fn($value) => $value !== null));

        return $playbook->fresh();
    }

    /**
     * Delete a playbook.
     */
    public function deletePlaybook(int $id): bool
    {
        $playbook = Playbook::findOrFail($id);
        return $playbook->delete();
    }

    /**
     * Duplicate a playbook.
     */
    public function duplicatePlaybook(int $id, string $newName): Playbook
    {
        $original = Playbook::with(['phases.tasks', 'tasks', 'goals'])->findOrFail($id);

        return DB::transaction(function () use ($original, $newName) {
            $duplicate = $original->replicate();
            $duplicate->name = $newName;
            $duplicate->slug = null; // Will be auto-generated
            $duplicate->created_by = Auth::id();
            $duplicate->save();

            // Duplicate phases and tasks
            foreach ($original->phases as $phase) {
                $newPhase = $phase->replicate();
                $newPhase->playbook_id = $duplicate->id;
                $newPhase->save();

                // Duplicate tasks in this phase
                foreach ($phase->tasks as $task) {
                    $newTask = $task->replicate();
                    $newTask->playbook_id = $duplicate->id;
                    $newTask->phase_id = $newPhase->id;
                    $newTask->save();
                }
            }

            // Duplicate standalone tasks (not in phases)
            foreach ($original->tasks()->whereNull('phase_id')->get() as $task) {
                $newTask = $task->replicate();
                $newTask->playbook_id = $duplicate->id;
                $newTask->save();
            }

            // Duplicate goals
            foreach ($original->goals as $goal) {
                $newGoal = $goal->replicate();
                $newGoal->playbook_id = $duplicate->id;
                $newGoal->save();
            }

            return $duplicate;
        });
    }

    // =========================================================================
    // COMMAND USE CASES - PHASES
    // =========================================================================

    /**
     * Create a phase for a playbook.
     */
    public function createPhase(int $playbookId, array $data): PlaybookPhase
    {
        return PlaybookPhase::create([
            'playbook_id' => $playbookId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_days' => $data['target_days'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
        ]);
    }

    /**
     * Update a phase.
     */
    public function updatePhase(int $id, array $data): PlaybookPhase
    {
        $phase = PlaybookPhase::findOrFail($id);

        $phase->update(array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'target_days' => $data['target_days'] ?? null,
            'display_order' => $data['display_order'] ?? null,
        ], fn($value) => $value !== null));

        return $phase->fresh();
    }

    /**
     * Delete a phase.
     */
    public function deletePhase(int $id): bool
    {
        $phase = PlaybookPhase::findOrFail($id);
        return $phase->delete();
    }

    // =========================================================================
    // COMMAND USE CASES - TASKS
    // =========================================================================

    /**
     * Create a task for a playbook.
     */
    public function createTask(int $playbookId, array $data): PlaybookTask
    {
        return PlaybookTask::create([
            'playbook_id' => $playbookId,
            'phase_id' => $data['phase_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'task_type' => $data['task_type'] ?? 'general',
            'task_config' => $data['task_config'] ?? [],
            'due_days' => $data['due_days'] ?? 0,
            'duration_estimate' => $data['duration_estimate'] ?? null,
            'is_required' => $data['is_required'] ?? true,
            'is_milestone' => $data['is_milestone'] ?? false,
            'assignee_type' => $data['assignee_type'] ?? 'owner',
            'assignee_id' => $data['assignee_id'] ?? null,
            'assignee_role' => $data['assignee_role'] ?? null,
            'dependencies' => $data['dependencies'] ?? [],
            'checklist' => $data['checklist'] ?? [],
            'resources' => $data['resources'] ?? [],
            'display_order' => $data['display_order'] ?? 0,
        ]);
    }

    /**
     * Update a task.
     */
    public function updateTask(int $id, array $data): PlaybookTask
    {
        $task = PlaybookTask::findOrFail($id);

        $task->update(array_filter([
            'phase_id' => $data['phase_id'] ?? null,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'task_type' => $data['task_type'] ?? null,
            'task_config' => $data['task_config'] ?? null,
            'due_days' => $data['due_days'] ?? null,
            'duration_estimate' => $data['duration_estimate'] ?? null,
            'is_required' => $data['is_required'] ?? null,
            'is_milestone' => $data['is_milestone'] ?? null,
            'assignee_type' => $data['assignee_type'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
            'assignee_role' => $data['assignee_role'] ?? null,
            'dependencies' => $data['dependencies'] ?? null,
            'checklist' => $data['checklist'] ?? null,
            'resources' => $data['resources'] ?? null,
            'display_order' => $data['display_order'] ?? null,
        ], fn($value) => $value !== null));

        return $task->fresh();
    }

    /**
     * Delete a task.
     */
    public function deleteTask(int $id): bool
    {
        $task = PlaybookTask::findOrFail($id);
        return $task->delete();
    }

    // =========================================================================
    // COMMAND USE CASES - INSTANCES
    // =========================================================================

    /**
     * Start a playbook instance for a record.
     */
    public function startInstance(int $playbookId, string $relatedModule, int $relatedId, ?int $ownerId = null): PlaybookInstance
    {
        $playbook = Playbook::with(['tasks'])->findOrFail($playbookId);

        return DB::transaction(function () use ($playbook, $relatedModule, $relatedId, $ownerId) {
            // Create instance
            $instance = PlaybookInstance::create([
                'playbook_id' => $playbook->id,
                'related_module' => $relatedModule,
                'related_id' => $relatedId,
                'status' => 'active',
                'started_at' => now(),
                'target_completion_at' => $playbook->estimated_days
                    ? now()->addDays($playbook->estimated_days)
                    : null,
                'owner_id' => $ownerId ?? $playbook->default_owner_id ?? Auth::id(),
                'progress_percent' => 0,
            ]);

            // Create task instances
            foreach ($playbook->tasks as $task) {
                $this->createTaskInstance($instance, $task);
            }

            // Log activity
            PlaybookActivity::log($instance, 'started', [
                'playbook_name' => $playbook->name,
            ]);

            return $instance->fresh();
        });
    }

    /**
     * Create a task instance from a playbook task.
     */
    protected function createTaskInstance(PlaybookInstance $instance, PlaybookTask $task): PlaybookTaskInstance
    {
        // Determine assignee
        $assignedTo = match ($task->assignee_type) {
            'specific' => $task->assignee_id,
            'owner' => $instance->owner_id,
            default => $instance->owner_id,
        };

        // Calculate due date
        $dueAt = $task->due_days > 0
            ? $instance->started_at->addDays($task->due_days)
            : null;

        return PlaybookTaskInstance::create([
            'instance_id' => $instance->id,
            'task_id' => $task->id,
            'status' => 'pending',
            'due_at' => $dueAt,
            'assigned_to' => $assignedTo,
        ]);
    }

    /**
     * Update an instance.
     */
    public function updateInstance(int $id, array $data): PlaybookInstance
    {
        $instance = PlaybookInstance::findOrFail($id);

        $instance->update(array_filter([
            'owner_id' => $data['owner_id'] ?? null,
            'target_completion_at' => $data['target_completion_at'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ], fn($value) => $value !== null));

        return $instance->fresh();
    }

    /**
     * Complete an instance.
     */
    public function completeInstance(int $id): PlaybookInstance
    {
        return DB::transaction(function () use ($id) {
            $instance = PlaybookInstance::findOrFail($id);
            $instance->complete();

            PlaybookActivity::log($instance, 'completed', [
                'completion_time' => $instance->started_at->diffInDays($instance->completed_at),
            ]);

            return $instance->fresh();
        });
    }

    /**
     * Pause an instance.
     */
    public function pauseInstance(int $id, ?string $reason = null): PlaybookInstance
    {
        return DB::transaction(function () use ($id, $reason) {
            $instance = PlaybookInstance::findOrFail($id);
            $instance->pause();

            PlaybookActivity::log($instance, 'paused', [
                'reason' => $reason,
            ]);

            return $instance->fresh();
        });
    }

    /**
     * Resume an instance.
     */
    public function resumeInstance(int $id): PlaybookInstance
    {
        return DB::transaction(function () use ($id) {
            $instance = PlaybookInstance::findOrFail($id);
            $instance->resume();

            PlaybookActivity::log($instance, 'resumed');

            return $instance->fresh();
        });
    }

    /**
     * Cancel an instance.
     */
    public function cancelInstance(int $id, ?string $reason = null): PlaybookInstance
    {
        return DB::transaction(function () use ($id, $reason) {
            $instance = PlaybookInstance::findOrFail($id);
            $instance->cancel();

            PlaybookActivity::log($instance, 'cancelled', [
                'reason' => $reason,
            ]);

            return $instance->fresh();
        });
    }

    // =========================================================================
    // COMMAND USE CASES - TASK INSTANCES
    // =========================================================================

    /**
     * Start a task instance.
     */
    public function startTaskInstance(int $taskInstanceId): PlaybookTaskInstance
    {
        return DB::transaction(function () use ($taskInstanceId) {
            $taskInstance = PlaybookTaskInstance::with(['task', 'instance'])->findOrFail($taskInstanceId);
            $taskInstance->start();

            PlaybookActivity::log(
                $taskInstance->instance,
                'task_started',
                ['task_title' => $taskInstance->task->title],
                $taskInstance
            );

            return $taskInstance->fresh();
        });
    }

    /**
     * Complete a task instance.
     */
    public function completeTaskInstance(int $taskInstanceId, ?string $notes = null, ?int $timeSpent = null): PlaybookTaskInstance
    {
        return DB::transaction(function () use ($taskInstanceId, $notes, $timeSpent) {
            $taskInstance = PlaybookTaskInstance::with(['task', 'instance'])->findOrFail($taskInstanceId);
            $taskInstance->complete(Auth::id(), $notes, $timeSpent);

            PlaybookActivity::log(
                $taskInstance->instance,
                'task_completed',
                [
                    'task_title' => $taskInstance->task->title,
                    'time_spent' => $timeSpent,
                ],
                $taskInstance
            );

            return $taskInstance->fresh();
        });
    }

    /**
     * Skip a task instance.
     */
    public function skipTaskInstance(int $taskInstanceId, ?string $reason = null): PlaybookTaskInstance
    {
        return DB::transaction(function () use ($taskInstanceId, $reason) {
            $taskInstance = PlaybookTaskInstance::with(['task', 'instance'])->findOrFail($taskInstanceId);
            $taskInstance->skip($reason);

            PlaybookActivity::log(
                $taskInstance->instance,
                'task_skipped',
                [
                    'task_title' => $taskInstance->task->title,
                    'reason' => $reason,
                ],
                $taskInstance
            );

            return $taskInstance->fresh();
        });
    }

    /**
     * Update a task instance checklist.
     */
    public function updateTaskChecklist(int $taskInstanceId, int $itemIndex, bool $completed): PlaybookTaskInstance
    {
        $taskInstance = PlaybookTaskInstance::findOrFail($taskInstanceId);
        $taskInstance->updateChecklistItem($itemIndex, $completed);
        return $taskInstance->fresh();
    }

    // =========================================================================
    // COMMAND USE CASES - GOALS
    // =========================================================================

    /**
     * Create a goal for a playbook.
     */
    public function createGoal(int $playbookId, array $data): PlaybookGoal
    {
        return PlaybookGoal::create([
            'playbook_id' => $playbookId,
            'name' => $data['name'],
            'metric_type' => $data['metric_type'],
            'target_module' => $data['target_module'] ?? null,
            'target_field' => $data['target_field'] ?? null,
            'comparison_operator' => $data['comparison_operator'] ?? '>=',
            'target_value' => $data['target_value'],
            'target_days' => $data['target_days'] ?? null,
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Record a goal result for an instance.
     */
    public function recordGoalResult(int $instanceId, int $goalId, $actualValue): PlaybookGoalResult
    {
        $instance = PlaybookInstance::findOrFail($instanceId);
        $goal = PlaybookGoal::findOrFail($goalId);

        $achieved = $goal->evaluate($instance, $actualValue);

        return PlaybookGoalResult::create([
            'instance_id' => $instanceId,
            'goal_id' => $goalId,
            'actual_value' => $actualValue,
            'achieved' => $achieved,
            'achieved_at' => $achieved ? now() : null,
        ]);
    }

    // =========================================================================
    // ANALYTICS & REPORTING
    // =========================================================================

    /**
     * Get playbook analytics.
     */
    public function getPlaybookAnalytics(int $playbookId): array
    {
        $playbook = Playbook::findOrFail($playbookId);

        $totalInstances = $playbook->instances()->count();
        $activeInstances = $playbook->instances()->where('status', 'active')->count();
        $completedInstances = $playbook->instances()->where('status', 'completed')->count();
        $avgCompletionDays = $playbook->getAverageCompletionDays();

        // Task completion rate
        $allTaskInstances = PlaybookTaskInstance::whereHas('instance', function ($q) use ($playbookId) {
            $q->where('playbook_id', $playbookId);
        })->count();

        $completedTasks = PlaybookTaskInstance::whereHas('instance', function ($q) use ($playbookId) {
            $q->where('playbook_id', $playbookId);
        })->where('status', 'completed')->count();

        $taskCompletionRate = $allTaskInstances > 0
            ? round(($completedTasks / $allTaskInstances) * 100, 2)
            : 0;

        return [
            'playbook_id' => $playbookId,
            'playbook_name' => $playbook->name,
            'total_instances' => $totalInstances,
            'active_instances' => $activeInstances,
            'completed_instances' => $completedInstances,
            'completion_rate' => $totalInstances > 0
                ? round(($completedInstances / $totalInstances) * 100, 2)
                : 0,
            'avg_completion_days' => $avgCompletionDays,
            'task_completion_rate' => $taskCompletionRate,
        ];
    }

    /**
     * Get instance progress summary.
     */
    public function getInstanceProgress(int $instanceId): array
    {
        $instance = PlaybookInstance::with(['taskInstances.task'])->findOrFail($instanceId);

        $totalTasks = $instance->getTotalTaskCount();
        $completedTasks = $instance->getCompletedTaskCount();
        $overdueTasks = $instance->getOverdueTaskCount();

        $tasksByStatus = $instance->taskInstances()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'instance_id' => $instanceId,
            'status' => $instance->status,
            'progress_percent' => $instance->progress_percent,
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'overdue_tasks' => $overdueTasks,
            'tasks_by_status' => $tasksByStatus,
            'started_at' => $instance->started_at,
            'target_completion_at' => $instance->target_completion_at,
            'completed_at' => $instance->completed_at,
        ];
    }

    /**
     * Get user task summary.
     */
    public function getUserTaskSummary(int $userId): array
    {
        $pendingTasks = PlaybookTaskInstance::pending()
            ->where('assigned_to', $userId)
            ->count();

        $inProgressTasks = PlaybookTaskInstance::inProgress()
            ->where('assigned_to', $userId)
            ->count();

        $overdueTasks = PlaybookTaskInstance::overdue()
            ->where('assigned_to', $userId)
            ->count();

        $completedToday = PlaybookTaskInstance::completed()
            ->where('assigned_to', $userId)
            ->whereDate('completed_at', today())
            ->count();

        return [
            'user_id' => $userId,
            'pending_tasks' => $pendingTasks,
            'in_progress_tasks' => $inProgressTasks,
            'overdue_tasks' => $overdueTasks,
            'completed_today' => $completedToday,
        ];
    }
}
