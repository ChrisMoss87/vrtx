<?php

namespace App\Services\Playbook;

use Illuminate\Support\Facades\DB;

class PlaybookService
{
    /**
     * Start a playbook for a record
     */
    public function startPlaybook(
        Playbook $playbook,
        string $relatedModule,
        int $relatedId,
        ?int $ownerId = null
    ): PlaybookInstance {
        return DB::transaction(function () use ($playbook, $relatedModule, $relatedId, $ownerId) {
            $startedAt = now();
            $targetCompletion = $playbook->estimated_days
                ? $startedAt->copy()->addDays($playbook->estimated_days)
                : null;

            // Create the instance
            $instance = DB::table('playbook_instances')->insertGetId([
                'playbook_id' => $playbook->id,
                'related_module' => $relatedModule,
                'related_id' => $relatedId,
                'status' => 'active',
                'started_at' => $startedAt,
                'target_completion_at' => $targetCompletion,
                'owner_id' => $ownerId ?? $playbook->default_owner_id ?? auth()->id(),
                'progress_percent' => 0,
            ]);

            // Create task instances for all tasks
            $this->createTaskInstances($instance);

            // Initialize goal results
            $this->initializeGoalResults($instance);

            // Log activity
            PlaybookActivity::log($instance, 'playbook_started', [
                'playbook_name' => $playbook->name,
            ]);

            return $instance;
        });
    }

    /**
     * Create task instances for a playbook instance
     */
    protected function createTaskInstances(PlaybookInstance $instance): void
    {
        $tasks = $instance->playbook->tasks()->orderBy('display_order')->get();
        $startedAt = $instance->started_at;

        foreach ($tasks as $task) {
            $dueAt = $task->due_days
                ? $startedAt->copy()->addDays($task->due_days)
                : null;

            // Determine assignee
            $assignedTo = $this->resolveAssignee($task, $instance);

            DB::table('playbook_task_instances')->insertGetId([
                'instance_id' => $instance->id,
                'task_id' => $task->id,
                'status' => 'pending',
                'due_at' => $dueAt,
                'assigned_to' => $assignedTo,
                'checklist_status' => $task->checklist ? array_fill(0, count($task->checklist), false) : null,
            ]);
        }
    }

    /**
     * Resolve the assignee for a task
     */
    protected function resolveAssignee(PlaybookTask $task, PlaybookInstance $instance): ?int
    {
        return match ($task->assignee_type) {
            'owner' => $instance->owner_id,
            'specific_user' => $task->assignee_id,
            'role' => null, // Would need additional logic to find user with role
            default => $instance->owner_id,
        };
    }

    /**
     * Initialize goal results for a playbook instance
     */
    protected function initializeGoalResults(PlaybookInstance $instance): void
    {
        $goals = $instance->playbook->goals;

        foreach ($goals as $goal) {
            DB::table('playbook_goal_results')->insertGetId([
                'instance_id' => $instance->id,
                'goal_id' => $goal->id,
                'achieved' => false,
            ]);
        }
    }

    /**
     * Complete a task
     */
    public function completeTask(
        PlaybookTaskInstance $taskInstance,
        ?string $notes = null,
        ?int $timeSpent = null
    ): void {
        $taskInstance->complete(auth()->id(), $notes, $timeSpent);

        PlaybookActivity::log(
            $taskInstance->instance,
            'task_completed',
            [
                'task_title' => $taskInstance->task->title,
                'notes' => $notes,
                'time_spent' => $timeSpent,
            ],
            $taskInstance
        );

        // Check if playbook is complete
        $this->checkPlaybookCompletion($taskInstance->instance);

        // Update any blocked tasks that depended on this one
        $this->updateDependentTasks($taskInstance);
    }

    /**
     * Skip a task
     */
    public function skipTask(PlaybookTaskInstance $taskInstance, ?string $reason = null): void
    {
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

        // Check if playbook is complete
        $this->checkPlaybookCompletion($taskInstance->instance);

        // Update any blocked tasks that depended on this one
        $this->updateDependentTasks($taskInstance);
    }

    /**
     * Start a task
     */
    public function startTask(PlaybookTaskInstance $taskInstance): void
    {
        if (!$taskInstance->canStart()) {
            throw new \Exception('Cannot start task - dependencies not met');
        }

        $taskInstance->start();

        PlaybookActivity::log(
            $taskInstance->instance,
            'task_started',
            ['task_title' => $taskInstance->task->title],
            $taskInstance
        );
    }

    /**
     * Check if playbook should be marked complete
     */
    protected function checkPlaybookCompletion(PlaybookInstance $instance): void
    {
        $instance->refresh();

        $pendingTasks = $instance->taskInstances()
            ->whereIn('status', ['pending', 'in_progress', 'blocked'])
            ->count();

        if ($pendingTasks === 0) {
            $instance->complete();

            PlaybookActivity::log($instance, 'playbook_completed', [
                'total_days' => $instance->started_at->diffInDays($instance->completed_at),
            ]);

            // Evaluate goals
            $this->evaluateGoals($instance);
        }
    }

    /**
     * Update tasks that were blocked waiting for this task
     */
    protected function updateDependentTasks(PlaybookTaskInstance $completedTask): void
    {
        $instance = $completedTask->instance;
        $completedTaskId = $completedTask->task_id;

        // Find blocked tasks that depend on this one
        $blockedTasks = $instance->taskInstances()
            ->where('status', 'blocked')
            ->get();

        foreach ($blockedTasks as $blockedTask) {
            $dependencies = $blockedTask->task->dependencies ?? [];

            if (in_array($completedTaskId, $dependencies)) {
                // Check if all dependencies are now met
                if ($blockedTask->canStart()) {
                    $blockedTask->status = 'pending';
                    $blockedTask->save();
                }
            }
        }
    }

    /**
     * Evaluate all goals for a completed playbook
     */
    protected function evaluateGoals(PlaybookInstance $instance): void
    {
        foreach ($instance->goalResults as $result) {
            $goal = $result->goal;

            // Calculate actual value based on metric type
            $actualValue = $this->calculateGoalValue($goal, $instance);

            $achieved = $goal->evaluate($instance, $actualValue);

            $result->update([
                'actual_value' => $actualValue,
                'achieved' => $achieved,
                'achieved_at' => $achieved ? now() : null,
            ]);
        }
    }

    /**
     * Calculate the actual value for a goal
     */
    protected function calculateGoalValue(PlaybookGoal $goal, PlaybookInstance $instance): ?float
    {
        return match ($goal->metric_type) {
            'task_completion' => $instance->calculateProgress(),
            'time_to_complete' => $instance->started_at->diffInDays($instance->completed_at),
            'field_value' => $this->getRecordFieldValue($instance, $goal->target_field),
            default => null,
        };
    }

    /**
     * Get a field value from the related record
     */
    protected function getRecordFieldValue(PlaybookInstance $instance, string $field): ?float
    {
        $record = $instance->getRelatedRecord();
        if (!$record) {
            return null;
        }

        $data = $record->data ?? [];
        return $data[$field] ?? null;
    }

    /**
     * Reassign a task
     */
    public function reassignTask(PlaybookTaskInstance $taskInstance, int $userId): void
    {
        $oldAssignee = $taskInstance->assigned_to;
        $taskInstance->assigned_to = $userId;
        $taskInstance->save();

        PlaybookActivity::log(
            $taskInstance->instance,
            'task_reassigned',
            [
                'task_title' => $taskInstance->task->title,
                'from_user_id' => $oldAssignee,
                'to_user_id' => $userId,
            ],
            $taskInstance
        );
    }

    /**
     * Pause a playbook
     */
    public function pausePlaybook(PlaybookInstance $instance, ?string $reason = null): void
    {
        $instance->pause();

        PlaybookActivity::log($instance, 'playbook_paused', [
            'reason' => $reason,
        ]);
    }

    /**
     * Resume a playbook
     */
    public function resumePlaybook(PlaybookInstance $instance): void
    {
        $instance->resume();

        PlaybookActivity::log($instance, 'playbook_resumed');
    }

    /**
     * Cancel a playbook
     */
    public function cancelPlaybook(PlaybookInstance $instance, ?string $reason = null): void
    {
        $instance->cancel();

        PlaybookActivity::log($instance, 'playbook_cancelled', [
            'reason' => $reason,
        ]);
    }

    /**
     * Get overdue tasks for a user
     */
    public function getOverdueTasksForUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return PlaybookTaskInstance::with(['instance.playbook', 'task'])
            ->where('assigned_to', $userId)
            ->overdue()
            ->get();
    }

    /**
     * Get upcoming tasks for a user
     */
    public function getUpcomingTasksForUser(int $userId, int $days = 7): \Illuminate\Database\Eloquent\Collection
    {
        return PlaybookTaskInstance::with(['instance.playbook', 'task'])
            ->where('assigned_to', $userId)
            ->where('status', 'pending')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->addDays($days)])
            ->orderBy('due_at')
            ->get();
    }

    /**
     * Get playbook statistics
     */
    public function getPlaybookStats(Playbook $playbook): array
    {
        $instances = $playbook->instances();

        return [
            'total_instances' => $instances->count(),
            'active' => $instances->clone()->where('status', 'active')->count(),
            'completed' => $instances->clone()->where('status', 'completed')->count(),
            'cancelled' => $instances->clone()->where('status', 'cancelled')->count(),
            'average_completion_days' => $playbook->getAverageCompletionDays(),
            'task_count' => $playbook->getTaskCount(),
        ];
    }
}
