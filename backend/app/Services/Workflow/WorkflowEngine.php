<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Domain\Modules\Entities\ModuleRecord as DomainModuleRecord;
use App\Jobs\ExecuteWorkflowJob;
use App\Jobs\RetryWorkflowStepJob;
use App\Models\ModuleRecord;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowRunHistory;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepLog;
use App\Services\Workflow\Actions\ActionHandler;
use Illuminate\Support\Facades\Log;

/**
 * Main workflow execution engine.
 * Handles trigger detection, condition evaluation, and step execution.
 */
class WorkflowEngine
{
    protected ConditionEvaluator $conditionEvaluator;
    protected ActionHandler $actionHandler;

    public function __construct(
        ConditionEvaluator $conditionEvaluator,
        ActionHandler $actionHandler
    ) {
        $this->conditionEvaluator = $conditionEvaluator;
        $this->actionHandler = $actionHandler;
    }

    /**
     * Trigger workflows for a domain entity record event.
     * Used by RecordService for domain-driven architecture.
     */
    public function triggerForDomainRecord(
        string $eventType,
        DomainModuleRecord $record,
        ?array $oldData = null,
        ?int $userId = null
    ): array {
        $isCreate = $eventType === Workflow::TRIGGER_RECORD_CREATED;

        // Build record data array for workflow processing
        $recordData = [
            'id' => $record->id(),
            'module_id' => $record->moduleId(),
            'data' => $record->data(),
            'created_at' => $record->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $record->updatedAt()?->format('Y-m-d H:i:s'),
        ];

        return $this->triggerWorkflows(
            $eventType,
            $record->moduleId(),
            $record->id(),
            $recordData,
            $oldData,
            $userId,
            $isCreate
        );
    }

    /**
     * Trigger workflows specifically for field changes.
     * Detects which fields changed and triggers field_changed workflows.
     */
    public function triggerForFieldChanges(
        DomainModuleRecord $record,
        array $oldData,
        ?int $userId = null
    ): array {
        $newData = $record->data();
        $executionIds = [];

        // Find all field_changed workflows for this module
        $workflows = Workflow::active()
            ->forModule($record->moduleId())
            ->forTrigger(Workflow::TRIGGER_FIELD_CHANGED)
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($workflows as $workflow) {
            // Check if the workflow's watched fields have changed
            if (!$workflow->checkFieldChangedCondition($newData, $oldData)) {
                continue;
            }

            // Check rate limiting
            if (!$workflow->canExecuteToday()) {
                continue;
            }

            // Check run-once-per-record if enabled
            if ($workflow->run_once_per_record) {
                if ($workflow->hasRunForRecord($record->id(), 'ModuleRecord', Workflow::TRIGGER_FIELD_CHANGED)) {
                    continue;
                }
            }

            // Build context
            $context = $this->buildContextFromDomainRecord($record, $oldData, $userId);

            // Check workflow conditions
            if (!$this->conditionEvaluator->evaluate($workflow->conditions ?? [], $context)) {
                continue;
            }

            // Create execution
            $execution = $this->createExecutionFromDomainRecord($workflow, $record, $userId, Workflow::TRIGGER_FIELD_CHANGED);
            $executionIds[] = $execution->id;

            // Record run history if run_once_per_record is enabled
            if ($workflow->run_once_per_record) {
                $workflow->recordRunForRecord($record->id(), 'ModuleRecord', Workflow::TRIGGER_FIELD_CHANGED);
            }

            // Increment rate limiting counter
            $workflow->incrementTodayExecutions();

            // Execute workflow (sync or async with optional delay)
            $this->dispatchExecution($execution, $context, $workflow->delay_seconds);

            // Stop if this workflow has stop_on_first_match
            if ($workflow->stop_on_first_match) {
                break;
            }
        }

        return $executionIds;
    }

    /**
     * Dispatch workflow execution (sync, async, or delayed).
     */
    protected function dispatchExecution(WorkflowExecution $execution, array $context, int $delaySeconds = 0): void
    {
        if ($delaySeconds > 0) {
            // Dispatch delayed job
            ExecuteWorkflowJob::dispatch($execution, $context)->delay($delaySeconds);
        } else {
            // Execute synchronously for immediate execution
            $this->execute($execution, $context);
        }
    }

    /**
     * Queue a workflow execution for async processing.
     */
    public function queueExecution(WorkflowExecution $execution, ?array $context = null, int $delaySeconds = 0): void
    {
        $context = $context ?? $execution->context_data;

        if ($delaySeconds > 0) {
            ExecuteWorkflowJob::dispatch($execution, $context)->delay($delaySeconds);
        } else {
            ExecuteWorkflowJob::dispatch($execution, $context);
        }
    }

    /**
     * Central method to trigger workflows based on event type.
     */
    protected function triggerWorkflows(
        string $eventType,
        int $moduleId,
        ?int $recordId,
        array $recordData,
        ?array $oldData,
        ?int $userId,
        bool $isCreate = false
    ): array {
        $executionIds = [];

        // Get matching event types (e.g., record_saved matches both create and update)
        $eventTypes = $this->getMatchingEventTypes($eventType);

        // Find all active workflows that match any of the event types
        $workflows = Workflow::active()
            ->forModule($moduleId)
            ->where(function ($query) use ($eventTypes) {
                $query->whereIn('trigger_type', $eventTypes);
            })
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($workflows as $workflow) {
            if (!$workflow->shouldTriggerFor($eventType, $recordData['data'] ?? $recordData, $oldData, $isCreate)) {
                continue;
            }

            // Check rate limiting
            if (!$workflow->canExecuteToday()) {
                continue;
            }

            // Check run-once-per-record if enabled
            if ($workflow->run_once_per_record && $recordId) {
                if ($workflow->hasRunForRecord($recordId, 'ModuleRecord', $eventType)) {
                    continue;
                }
            }

            // Build context
            $context = $this->buildContextFromArray($recordData, $oldData, $userId);

            // Check workflow conditions
            if (!$this->conditionEvaluator->evaluate($workflow->conditions ?? [], $context)) {
                continue;
            }

            // Create execution
            $execution = WorkflowExecution::create([
                'workflow_id' => $workflow->id,
                'trigger_type' => $eventType,
                'trigger_record_id' => $recordId,
                'trigger_record_type' => 'ModuleRecord',
                'status' => WorkflowExecution::STATUS_PENDING,
                'context_data' => $context,
                'triggered_by' => $userId,
            ]);
            $executionIds[] = $execution->id;

            // Record run history if run_once_per_record is enabled
            if ($workflow->run_once_per_record && $recordId) {
                $workflow->recordRunForRecord($recordId, 'ModuleRecord', $eventType);
            }

            // Increment rate limiting counter
            $workflow->incrementTodayExecutions();

            // Execute workflow (sync or async with optional delay)
            $this->dispatchExecution($execution, $context, $workflow->delay_seconds);

            // Stop if this workflow has stop_on_first_match
            if ($workflow->stop_on_first_match) {
                break;
            }
        }

        return $executionIds;
    }

    /**
     * Get all event types that should match for a given event.
     */
    protected function getMatchingEventTypes(string $eventType): array
    {
        $types = [$eventType];

        // record_saved matches both create and update
        if ($eventType === Workflow::TRIGGER_RECORD_CREATED || $eventType === Workflow::TRIGGER_RECORD_UPDATED) {
            $types[] = Workflow::TRIGGER_RECORD_SAVED;
        }

        // field_changed should also be checked on updates
        if ($eventType === Workflow::TRIGGER_RECORD_UPDATED) {
            $types[] = Workflow::TRIGGER_FIELD_CHANGED;
        }

        return array_unique($types);
    }

    /**
     * Trigger workflows for a record event (Eloquent model version).
     * @deprecated Use triggerForDomainRecord instead for domain entities
     */
    public function triggerForEvent(
        string $eventType,
        ModuleRecord $record,
        ?array $oldData = null,
        ?int $userId = null
    ): array {
        $isCreate = $eventType === Workflow::TRIGGER_RECORD_CREATED;

        $recordData = [
            'id' => $record->id,
            'module_id' => $record->module_id,
            'data' => $record->data,
            'created_at' => $record->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $record->updated_at?->format('Y-m-d H:i:s'),
        ];

        return $this->triggerWorkflows(
            $eventType,
            $record->module_id,
            $record->id,
            $recordData,
            $oldData,
            $userId,
            $isCreate
        );
    }

    /**
     * Execute a workflow.
     */
    public function execute(WorkflowExecution $execution, ?array $context = null): bool
    {
        $workflow = $execution->workflow;
        $steps = $workflow->steps()->orderBy('order')->get();

        if ($steps->isEmpty()) {
            $execution->markAsCompleted();
            return true;
        }

        // Build context if not provided
        if ($context === null) {
            $context = $execution->context_data ?? [];
        }

        $execution->markAsStarted();

        try {
            // Execute each step
            foreach ($steps as $step) {
                $stepLog = $this->createStepLog($execution, $step);

                // Check step conditions
                if (!empty($step->conditions)) {
                    if (!$this->conditionEvaluator->evaluate($step->conditions, $context)) {
                        $stepLog->markAsSkipped('Step conditions not met');
                        continue;
                    }
                }

                // Execute the step
                $success = $this->executeStep($step, $stepLog, $context);

                if (!$success && !$step->continue_on_error) {
                    // Stop execution if step failed and continue_on_error is false
                    $execution->markAsFailed("Step '{$step->name}' failed");
                    return false;
                }

                // Update context with step output
                if ($stepLog->output_data) {
                    $context = array_merge($context, [
                        'step_outputs' => array_merge(
                            $context['step_outputs'] ?? [],
                            [$step->id => $stepLog->output_data]
                        )
                    ]);
                }
            }

            $execution->markAsCompleted();
            return true;

        } catch (\Exception $e) {
            Log::error('Workflow execution failed', [
                'execution_id' => $execution->id,
                'workflow_id' => $workflow->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $execution->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Execute a single step.
     */
    protected function executeStep(WorkflowStep $step, WorkflowStepLog $stepLog, array $context): bool
    {
        $stepLog->markAsStarted($context);

        try {
            // Handle the action
            $result = $this->actionHandler->handle(
                $step->action_type,
                $step->action_config,
                $context
            );

            $stepLog->markAsCompleted($result);
            return true;

        } catch (\Exception $e) {
            $stepLog->markAsFailed($e->getMessage(), $e->getTraceAsString());

            // Check if we should retry
            if ($stepLog->canRetry()) {
                $retryLog = $stepLog->createRetry();
                // Schedule retry with delay
                RetryWorkflowStepJob::dispatch($retryLog)->delay($step->retry_delay_seconds ?? 60);
            }

            return false;
        }
    }

    /**
     * Create a workflow execution record.
     */
    protected function createExecution(
        Workflow $workflow,
        ?ModuleRecord $record = null,
        ?int $userId = null
    ): WorkflowExecution {
        return WorkflowExecution::create([
            'workflow_id' => $workflow->id,
            'trigger_type' => $record ? 'record_event' : 'manual',
            'trigger_record_id' => $record?->id,
            'trigger_record_type' => $record ? ModuleRecord::class : null,
            'status' => WorkflowExecution::STATUS_PENDING,
            'context_data' => $record ? $this->buildContext($record, null, $userId) : [],
            'triggered_by' => $userId,
        ]);
    }

    /**
     * Create a workflow execution from a domain record.
     */
    protected function createExecutionFromDomainRecord(
        Workflow $workflow,
        DomainModuleRecord $record,
        ?int $userId = null,
        string $triggerType = 'record_event'
    ): WorkflowExecution {
        return WorkflowExecution::create([
            'workflow_id' => $workflow->id,
            'trigger_type' => $triggerType,
            'trigger_record_id' => $record->id(),
            'trigger_record_type' => 'ModuleRecord',
            'status' => WorkflowExecution::STATUS_PENDING,
            'context_data' => $this->buildContextFromDomainRecord($record, null, $userId),
            'triggered_by' => $userId,
        ]);
    }

    /**
     * Create a step log record.
     */
    protected function createStepLog(WorkflowExecution $execution, WorkflowStep $step): WorkflowStepLog
    {
        return WorkflowStepLog::create([
            'execution_id' => $execution->id,
            'step_id' => $step->id,
            'status' => WorkflowStepLog::STATUS_PENDING,
        ]);
    }

    /**
     * Build context data from a domain record.
     */
    protected function buildContextFromDomainRecord(
        DomainModuleRecord $record,
        ?array $oldData = null,
        ?int $userId = null
    ): array {
        $newData = $record->data();

        return [
            'record' => array_merge(['id' => $record->id(), 'module_id' => $record->moduleId()], $newData),
            'record_id' => $record->id(),
            'module_id' => $record->moduleId(),
            'old_data' => $oldData,
            'changes' => $oldData ? $this->getChanges($oldData, $newData) : [],
            'changed_fields' => $oldData ? array_keys($this->getChanges($oldData, $newData)) : [],
            'user_id' => $userId,
            'current_user' => $userId,
            'timestamp' => now()->toISOString(),
            'now' => [
                'date' => now()->toDateString(),
                'time' => now()->toTimeString(),
                'datetime' => now()->toDateTimeString(),
                'timestamp' => now()->timestamp,
            ],
            'step_outputs' => [],
        ];
    }

    /**
     * Build context data from an array.
     */
    protected function buildContextFromArray(
        array $recordData,
        ?array $oldData = null,
        ?int $userId = null
    ): array {
        $data = $recordData['data'] ?? $recordData;

        return [
            'record' => array_merge(['id' => $recordData['id'] ?? null, 'module_id' => $recordData['module_id'] ?? null], $data),
            'record_id' => $recordData['id'] ?? null,
            'module_id' => $recordData['module_id'] ?? null,
            'old_data' => $oldData,
            'changes' => $oldData ? $this->getChanges($oldData, $data) : [],
            'changed_fields' => $oldData ? array_keys($this->getChanges($oldData, $data)) : [],
            'user_id' => $userId,
            'current_user' => $userId,
            'timestamp' => now()->toISOString(),
            'now' => [
                'date' => now()->toDateString(),
                'time' => now()->toTimeString(),
                'datetime' => now()->toDateTimeString(),
                'timestamp' => now()->timestamp,
            ],
            'step_outputs' => [],
        ];
    }

    /**
     * Build context data for workflow execution (Eloquent version).
     * @deprecated Use buildContextFromDomainRecord instead
     */
    protected function buildContext(
        ModuleRecord $record,
        ?array $oldData = null,
        ?int $userId = null
    ): array {
        return [
            'record' => [
                'id' => $record->id,
                'module_id' => $record->module_id,
                'data' => $record->data,
                'created_at' => $record->created_at?->toISOString(),
                'updated_at' => $record->updated_at?->toISOString(),
            ],
            'old_data' => $oldData,
            'changes' => $oldData ? $this->getChanges($oldData, $record->data) : [],
            'user_id' => $userId,
            'timestamp' => now()->toISOString(),
            'step_outputs' => [],
        ];
    }

    /**
     * Get the fields that changed between old and new data.
     */
    protected function getChanges(array $oldData, array $newData): array
    {
        $changes = [];

        foreach ($newData as $key => $newValue) {
            $oldValue = $oldData[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        // Check for removed fields
        foreach ($oldData as $key => $oldValue) {
            if (!array_key_exists($key, $newData)) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => null,
                ];
            }
        }

        return $changes;
    }

    /**
     * Manually trigger a workflow for a record.
     */
    public function triggerManually(
        Workflow $workflow,
        int $recordId,
        int $moduleId,
        array $recordData,
        ?int $userId = null
    ): ?WorkflowExecution {
        // Check if workflow allows manual trigger
        if (!$workflow->allow_manual_trigger) {
            return null;
        }

        // Check rate limiting
        if (!$workflow->canExecuteToday()) {
            return null;
        }

        // Build context
        $context = $this->buildContextFromArray([
            'id' => $recordId,
            'module_id' => $moduleId,
            'data' => $recordData,
        ], null, $userId);

        // Create execution
        $execution = WorkflowExecution::create([
            'workflow_id' => $workflow->id,
            'trigger_type' => Workflow::TRIGGER_MANUAL,
            'trigger_record_id' => $recordId,
            'trigger_record_type' => 'ModuleRecord',
            'status' => WorkflowExecution::STATUS_PENDING,
            'context_data' => $context,
            'triggered_by' => $userId,
        ]);

        // Increment rate limiting counter
        $workflow->incrementTodayExecutions();

        // Execute
        $this->execute($execution, $context);

        return $execution;
    }

    /**
     * Get execution statistics for a workflow.
     */
    public function getWorkflowStats(Workflow $workflow): array
    {
        $executions = $workflow->executions();

        return [
            'total_executions' => $workflow->execution_count,
            'successful_executions' => $workflow->success_count,
            'failed_executions' => $workflow->failure_count,
            'success_rate' => $workflow->execution_count > 0
                ? round(($workflow->success_count / $workflow->execution_count) * 100, 2)
                : 0,
            'last_run_at' => $workflow->last_run_at?->toISOString(),
            'executions_today' => $workflow->executions_today,
            'max_executions_per_day' => $workflow->max_executions_per_day,
            'recent_executions' => $executions->recent(7)->count(),
        ];
    }
}
