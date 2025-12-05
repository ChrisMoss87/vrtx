<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStepLog extends Model
{
    use HasFactory;

    // Step execution statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'execution_id',
        'step_id',
        'status',
        'started_at',
        'completed_at',
        'duration_ms',
        'input_data',
        'output_data',
        'error_message',
        'error_trace',
        'retry_attempt',
    ];

    protected $casts = [
        'execution_id' => 'integer',
        'step_id' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_ms' => 'integer',
        'input_data' => 'array',
        'output_data' => 'array',
        'retry_attempt' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'retry_attempt' => 0,
    ];

    /**
     * Get the execution this log belongs to.
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'execution_id');
    }

    /**
     * Get the step this log belongs to.
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id');
    }

    /**
     * Mark step as started.
     */
    public function markAsStarted(array $inputData = []): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
            'input_data' => $inputData,
        ]);
    }

    /**
     * Mark step as completed.
     */
    public function markAsCompleted(array $outputData = []): void
    {
        $completedAt = now();
        $durationMs = $this->started_at
            ? $completedAt->diffInMilliseconds($this->started_at)
            : null;

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => $completedAt,
            'duration_ms' => $durationMs,
            'output_data' => $outputData,
        ]);

        $this->execution->incrementStepCompleted();
    }

    /**
     * Mark step as failed.
     */
    public function markAsFailed(string $errorMessage, ?string $errorTrace = null): void
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
            'error_trace' => $errorTrace,
        ]);

        $this->execution->incrementStepFailed();
    }

    /**
     * Mark step as skipped.
     */
    public function markAsSkipped(string $reason = 'Condition not met'): void
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'completed_at' => now(),
            'output_data' => ['skip_reason' => $reason],
        ]);

        $this->execution->incrementStepSkipped();
    }

    /**
     * Check if step can be retried.
     */
    public function canRetry(): bool
    {
        $step = $this->step;
        return $this->status === self::STATUS_FAILED
            && $step->retry_count > 0
            && $this->retry_attempt < $step->retry_count;
    }

    /**
     * Create a retry attempt.
     */
    public function createRetry(): self
    {
        return self::create([
            'execution_id' => $this->execution_id,
            'step_id' => $this->step_id,
            'status' => self::STATUS_PENDING,
            'retry_attempt' => $this->retry_attempt + 1,
        ]);
    }
}
