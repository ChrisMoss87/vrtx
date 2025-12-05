<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowExecution extends Model
{
    use HasFactory;

    // Execution statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    // Trigger sources
    public const TRIGGER_RECORD_EVENT = 'record_event';
    public const TRIGGER_SCHEDULED = 'scheduled';
    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_WEBHOOK = 'webhook';

    protected $fillable = [
        'workflow_id',
        'trigger_type',
        'trigger_record_id',
        'trigger_record_type',
        'status',
        'queued_at',
        'started_at',
        'completed_at',
        'duration_ms',
        'context_data',
        'steps_completed',
        'steps_failed',
        'steps_skipped',
        'error_message',
        'triggered_by',
    ];

    protected $casts = [
        'workflow_id' => 'integer',
        'trigger_record_id' => 'integer',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_ms' => 'integer',
        'context_data' => 'array',
        'steps_completed' => 'integer',
        'steps_failed' => 'integer',
        'steps_skipped' => 'integer',
        'triggered_by' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'context_data' => '{}',
        'steps_completed' => 0,
        'steps_failed' => 0,
        'steps_skipped' => 0,
    ];

    /**
     * Get the workflow this execution belongs to.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the step logs for this execution.
     */
    public function stepLogs(): HasMany
    {
        return $this->hasMany(WorkflowStepLog::class, 'execution_id');
    }

    /**
     * Get the user who triggered this execution.
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * Get the trigger record (polymorphic).
     */
    public function triggerRecord()
    {
        if (!$this->trigger_record_type || !$this->trigger_record_id) {
            return null;
        }

        return $this->morphTo('trigger_record', 'trigger_record_type', 'trigger_record_id');
    }

    /**
     * Mark execution as started.
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark execution as completed.
     */
    public function markAsCompleted(): void
    {
        $completedAt = now();
        $durationMs = $this->started_at
            ? $completedAt->diffInMilliseconds($this->started_at)
            : null;

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => $completedAt,
            'duration_ms' => $durationMs,
        ]);

        $this->workflow->recordExecution(true);
    }

    /**
     * Mark execution as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $completedAt = now();
        $durationMs = $this->started_at
            ? $completedAt->diffInMilliseconds($this->started_at)
            : null;

        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => $completedAt,
            'duration_ms' => $durationMs,
            'error_message' => $errorMessage,
        ]);

        $this->workflow->recordExecution(false);
    }

    /**
     * Mark execution as cancelled.
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Increment step counters.
     */
    public function incrementStepCompleted(): void
    {
        $this->increment('steps_completed');
    }

    public function incrementStepFailed(): void
    {
        $this->increment('steps_failed');
    }

    public function incrementStepSkipped(): void
    {
        $this->increment('steps_skipped');
    }

    /**
     * Check if execution is still running.
     */
    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_QUEUED, self::STATUS_RUNNING]);
    }

    /**
     * Check if execution has finished.
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }

    /**
     * Scope to get recent executions.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
