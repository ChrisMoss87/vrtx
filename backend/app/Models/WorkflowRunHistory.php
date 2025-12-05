<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks which workflows have run for specific records.
 * Used to implement "run once per record" functionality.
 */
class WorkflowRunHistory extends Model
{
    use HasFactory;

    protected $table = 'workflow_run_history';

    protected $fillable = [
        'workflow_id',
        'record_id',
        'record_type',
        'trigger_type',
        'executed_at',
    ];

    protected $casts = [
        'workflow_id' => 'integer',
        'record_id' => 'integer',
        'executed_at' => 'datetime',
    ];

    /**
     * Get the workflow this history entry belongs to.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Scope to filter by workflow.
     */
    public function scopeForWorkflow($query, int $workflowId)
    {
        return $query->where('workflow_id', $workflowId);
    }

    /**
     * Scope to filter by record.
     */
    public function scopeForRecord($query, int $recordId, string $recordType)
    {
        return $query->where('record_id', $recordId)
            ->where('record_type', $recordType);
    }

    /**
     * Scope to filter by trigger type.
     */
    public function scopeForTrigger($query, string $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }

    /**
     * Check if a workflow has already run for a specific record.
     */
    public static function hasRun(int $workflowId, int $recordId, string $recordType, ?string $triggerType = null): bool
    {
        $query = static::where('workflow_id', $workflowId)
            ->where('record_id', $recordId)
            ->where('record_type', $recordType);

        if ($triggerType) {
            $query->where('trigger_type', $triggerType);
        }

        return $query->exists();
    }

    /**
     * Record that a workflow has run for a specific record.
     */
    public static function recordRun(int $workflowId, int $recordId, string $recordType, string $triggerType): static
    {
        return static::create([
            'workflow_id' => $workflowId,
            'record_id' => $recordId,
            'record_type' => $recordType,
            'trigger_type' => $triggerType,
            'executed_at' => now(),
        ]);
    }

    /**
     * Clear run history for a specific workflow.
     */
    public static function clearForWorkflow(int $workflowId): int
    {
        return static::where('workflow_id', $workflowId)->delete();
    }

    /**
     * Clear run history for a specific record across all workflows.
     */
    public static function clearForRecord(int $recordId, string $recordType): int
    {
        return static::where('record_id', $recordId)
            ->where('record_type', $recordType)
            ->delete();
    }
}
