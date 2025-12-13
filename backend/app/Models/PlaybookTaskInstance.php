<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaybookTaskInstance extends Model
{
    protected $fillable = [
        'instance_id',
        'task_id',
        'status',
        'due_at',
        'started_at',
        'completed_at',
        'assigned_to',
        'completed_by',
        'notes',
        'checklist_status',
        'time_spent',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'checklist_status' => 'array',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(PlaybookInstance::class, 'instance_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(PlaybookTask::class, 'task_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }

    public function start(): void
    {
        $this->status = 'in_progress';
        $this->started_at = now();
        $this->save();
    }

    public function complete(?int $userId = null, ?string $notes = null, ?int $timeSpent = null): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->completed_by = $userId ?? auth()->id();

        if ($notes) {
            $this->notes = $notes;
        }

        if ($timeSpent) {
            $this->time_spent = $timeSpent;
        }

        $this->save();

        // Update instance progress
        $this->instance->updateProgress();
    }

    public function skip(?string $reason = null): void
    {
        $this->status = 'skipped';
        $this->completed_at = now();
        $this->notes = $reason;
        $this->save();

        // Update instance progress
        $this->instance->updateProgress();
    }

    public function block(): void
    {
        $this->status = 'blocked';
        $this->save();
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending'
            && $this->due_at
            && $this->due_at->isPast();
    }

    public function canStart(): bool
    {
        $task = $this->task;

        if (empty($task->dependencies)) {
            return true;
        }

        // Check if all dependency tasks are completed
        $dependencyTaskIds = $task->dependencies;
        $completedDependencies = $this->instance->taskInstances()
            ->whereIn('task_id', $dependencyTaskIds)
            ->whereIn('status', ['completed', 'skipped'])
            ->count();

        return $completedDependencies === count($dependencyTaskIds);
    }

    public function updateChecklistItem(int $index, bool $completed): void
    {
        $status = $this->checklist_status ?? [];
        $status[$index] = $completed;
        $this->checklist_status = $status;
        $this->save();
    }

    public function getChecklistProgress(): array
    {
        $task = $this->task;
        $checklist = $task->checklist ?? [];
        $status = $this->checklist_status ?? [];

        $total = count($checklist);
        $completed = count(array_filter($status));

        return [
            'total' => $total,
            'completed' => $completed,
            'percent' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }
}
