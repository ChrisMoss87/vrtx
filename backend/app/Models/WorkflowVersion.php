<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'version_number',
        'name',
        'description',
        'workflow_data',
        'steps_data',
        'trigger_type',
        'trigger_config',
        'conditions',
        'is_active_version',
        'change_summary',
        'change_type',
        'created_by',
    ];

    protected $casts = [
        'workflow_data' => 'array',
        'steps_data' => 'array',
        'trigger_config' => 'array',
        'conditions' => 'array',
        'is_active_version' => 'boolean',
        'version_number' => 'integer',
    ];

    // Change types
    public const CHANGE_CREATE = 'create';
    public const CHANGE_UPDATE = 'update';
    public const CHANGE_ROLLBACK = 'rollback';
    public const CHANGE_RESTORE = 'restore';

    /**
     * Get the workflow this version belongs to.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the user who created this version.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Create a version snapshot from a workflow.
     */
    public static function createFromWorkflow(
        Workflow $workflow,
        string $changeType = self::CHANGE_UPDATE,
        ?string $changeSummary = null,
        ?int $userId = null
    ): self {
        // Get the next version number
        $nextVersion = self::where('workflow_id', $workflow->id)
            ->max('version_number') + 1;

        // Snapshot the current workflow state
        $workflowData = [
            'name' => $workflow->name,
            'description' => $workflow->description,
            'module_id' => $workflow->module_id,
            'is_active' => $workflow->is_active,
            'priority' => $workflow->priority,
            'trigger_type' => $workflow->trigger_type,
            'trigger_config' => $workflow->trigger_config,
            'trigger_timing' => $workflow->trigger_timing,
            'watched_fields' => $workflow->watched_fields,
            'stop_on_first_match' => $workflow->stop_on_first_match,
            'max_executions_per_day' => $workflow->max_executions_per_day,
            'conditions' => $workflow->conditions,
            'run_once_per_record' => $workflow->run_once_per_record,
            'allow_manual_trigger' => $workflow->allow_manual_trigger,
            'delay_seconds' => $workflow->delay_seconds,
            'schedule_cron' => $workflow->schedule_cron,
        ];

        // Snapshot the steps
        $stepsData = $workflow->steps->map(function ($step) {
            return [
                'id' => $step->id,
                'order' => $step->order,
                'name' => $step->name,
                'description' => $step->description,
                'action_type' => $step->action_type,
                'action_config' => $step->action_config,
                'conditions' => $step->conditions,
                'branch_id' => $step->branch_id,
                'is_parallel' => $step->is_parallel,
                'continue_on_error' => $step->continue_on_error,
                'retry_count' => $step->retry_count,
                'retry_delay_seconds' => $step->retry_delay_seconds,
            ];
        })->toArray();

        // Mark previous active version as inactive
        self::where('workflow_id', $workflow->id)
            ->where('is_active_version', true)
            ->update(['is_active_version' => false]);

        // Create the new version
        $version = self::create([
            'workflow_id' => $workflow->id,
            'version_number' => $nextVersion,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'workflow_data' => $workflowData,
            'steps_data' => $stepsData,
            'trigger_type' => $workflow->trigger_type,
            'trigger_config' => $workflow->trigger_config,
            'conditions' => $workflow->conditions,
            'is_active_version' => true,
            'change_summary' => $changeSummary,
            'change_type' => $changeType,
            'created_by' => $userId ?? auth()->id(),
        ]);

        // Update workflow's version tracking
        $workflow->update([
            'current_version' => $nextVersion,
            'active_version_id' => $version->id,
        ]);

        return $version;
    }

    /**
     * Restore this version to the workflow.
     */
    public function restore(?int $userId = null): Workflow
    {
        $workflow = $this->workflow;

        // Update workflow with this version's data
        $workflow->update([
            'name' => $this->workflow_data['name'],
            'description' => $this->workflow_data['description'],
            'module_id' => $this->workflow_data['module_id'],
            'is_active' => $this->workflow_data['is_active'],
            'priority' => $this->workflow_data['priority'],
            'trigger_type' => $this->workflow_data['trigger_type'],
            'trigger_config' => $this->workflow_data['trigger_config'],
            'trigger_timing' => $this->workflow_data['trigger_timing'] ?? 'all',
            'watched_fields' => $this->workflow_data['watched_fields'],
            'stop_on_first_match' => $this->workflow_data['stop_on_first_match'],
            'max_executions_per_day' => $this->workflow_data['max_executions_per_day'],
            'conditions' => $this->workflow_data['conditions'],
            'run_once_per_record' => $this->workflow_data['run_once_per_record'],
            'allow_manual_trigger' => $this->workflow_data['allow_manual_trigger'],
            'delay_seconds' => $this->workflow_data['delay_seconds'],
            'schedule_cron' => $this->workflow_data['schedule_cron'],
        ]);

        // Delete current steps and recreate from version
        $workflow->steps()->delete();

        foreach ($this->steps_data as $stepData) {
            $workflow->steps()->create([
                'order' => $stepData['order'],
                'name' => $stepData['name'],
                'description' => $stepData['description'] ?? null,
                'action_type' => $stepData['action_type'],
                'action_config' => $stepData['action_config'],
                'conditions' => $stepData['conditions'] ?? null,
                'branch_id' => $stepData['branch_id'] ?? null,
                'is_parallel' => $stepData['is_parallel'] ?? false,
                'continue_on_error' => $stepData['continue_on_error'] ?? false,
                'retry_count' => $stepData['retry_count'] ?? 0,
                'retry_delay_seconds' => $stepData['retry_delay_seconds'] ?? 60,
            ]);
        }

        // Create a new version to track the restoration
        self::createFromWorkflow(
            $workflow->fresh(),
            self::CHANGE_RESTORE,
            "Restored from version {$this->version_number}",
            $userId
        );

        return $workflow->fresh();
    }

    /**
     * Get a human-readable diff from the previous version.
     */
    public function getDiffFromPrevious(): array
    {
        $previousVersion = self::where('workflow_id', $this->workflow_id)
            ->where('version_number', $this->version_number - 1)
            ->first();

        if (!$previousVersion) {
            return ['type' => 'initial', 'changes' => ['Initial version']];
        }

        $changes = [];

        // Compare workflow data
        if ($this->name !== $previousVersion->name) {
            $changes[] = "Name changed from \"{$previousVersion->name}\" to \"{$this->name}\"";
        }

        if ($this->trigger_type !== $previousVersion->trigger_type) {
            $changes[] = "Trigger type changed from \"{$previousVersion->trigger_type}\" to \"{$this->trigger_type}\"";
        }

        // Compare step counts
        $oldStepCount = count($previousVersion->steps_data);
        $newStepCount = count($this->steps_data);

        if ($oldStepCount !== $newStepCount) {
            $diff = $newStepCount - $oldStepCount;
            if ($diff > 0) {
                $changes[] = "{$diff} step(s) added";
            } else {
                $changes[] = abs($diff) . " step(s) removed";
            }
        }

        // Check for modified steps
        $modifiedSteps = 0;
        foreach ($this->steps_data as $index => $step) {
            if (isset($previousVersion->steps_data[$index])) {
                $prevStep = $previousVersion->steps_data[$index];
                if ($step['action_type'] !== $prevStep['action_type'] ||
                    json_encode($step['action_config']) !== json_encode($prevStep['action_config'])) {
                    $modifiedSteps++;
                }
            }
        }

        if ($modifiedSteps > 0) {
            $changes[] = "{$modifiedSteps} step(s) modified";
        }

        return [
            'type' => 'diff',
            'changes' => $changes ?: ['No significant changes detected'],
        ];
    }

    /**
     * Scope to get versions for a specific workflow.
     */
    public function scopeForWorkflow($query, int $workflowId)
    {
        return $query->where('workflow_id', $workflowId);
    }

    /**
     * Scope to get active versions only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active_version', true);
    }
}
