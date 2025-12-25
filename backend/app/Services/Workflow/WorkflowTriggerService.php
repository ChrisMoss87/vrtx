<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Domain\Workflow\Entities\WorkflowExecution;
use App\Domain\Workflow\Repositories\WorkflowExecutionRepositoryInterface;
use App\Domain\Workflow\ValueObjects\ExecutionStatus;
use App\Jobs\ExecuteWorkflowJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Service for triggering workflows based on record events.
 *
 * This service is called from the ModuleRecordObserver to find and trigger
 * matching workflows when records are created, updated, or deleted.
 */
class WorkflowTriggerService
{
    public function __construct(
        private readonly WorkflowExecutionRepositoryInterface $executionRepository,
    ) {}

    /**
     * Trigger workflows for a record creation event.
     */
    public function onRecordCreated(ModuleRecord $record): void
    {
        $this->triggerWorkflows(
            record: $record,
            eventType: Workflow::TRIGGER_RECORD_CREATED,
            oldData: null,
            isCreate: true,
        );
    }

    /**
     * Trigger workflows for a record update event.
     */
    public function onRecordUpdated(ModuleRecord $record, ?array $oldData): void
    {
        $this->triggerWorkflows(
            record: $record,
            eventType: Workflow::TRIGGER_RECORD_UPDATED,
            oldData: $oldData,
            isCreate: false,
        );
    }

    /**
     * Trigger workflows for a record deletion event.
     */
    public function onRecordDeleted(ModuleRecord $record): void
    {
        $this->triggerWorkflows(
            record: $record,
            eventType: Workflow::TRIGGER_RECORD_DELETED,
            oldData: $record->data,
            isCreate: false,
        );
    }

    /**
     * Find and trigger matching workflows for a record event.
     */
    protected function triggerWorkflows(
        ModuleRecord $record,
        string $eventType,
        ?array $oldData,
        bool $isCreate,
    ): void {
        $moduleId = $record->module_id;
        $recordData = $record->data ?? [];

        // Find active workflows for this module that could match this event
        $workflows = $this->findMatchingWorkflows($moduleId, $eventType, $recordData, $oldData, $isCreate);

        foreach ($workflows as $workflow) {
            $this->dispatchWorkflow($workflow, $record, $eventType, $oldData);
        }
    }

    /**
     * Find workflows that should trigger for the given event.
     */
    protected function findMatchingWorkflows(
        int $moduleId,
        string $eventType,
        array $recordData,
        ?array $oldData,
        bool $isCreate,
    ): array {
        // Get all active workflows for this module
        $workflows = DB::table('workflows')
            ->active()
            ->forModule($moduleId)
            ->orderBy('priority', 'desc')
            ->get();

        $matchingWorkflows = [];

        foreach ($workflows as $workflow) {
            if ($workflow->shouldTriggerFor($eventType, $recordData, $oldData, $isCreate)) {
                $matchingWorkflows[] = $workflow;

                // If stop_on_first_match is enabled, stop after first match
                if ($workflow->stop_on_first_match) {
                    break;
                }
            }
        }

        return $matchingWorkflows;
    }

    /**
     * Dispatch a workflow execution for a matched workflow.
     */
    protected function dispatchWorkflow(
        Workflow $workflow,
        ModuleRecord $record,
        string $eventType,
        ?array $oldData,
    ): void {
        // Check run-once-per-record constraint
        if ($workflow->run_once_per_record) {
            if ($workflow->hasRunForRecord($record->id, ModuleRecord::class, $eventType)) {
                Log::debug('Workflow skipped - already run for this record', [
                    'workflow_id' => $workflow->id,
                    'record_id' => $record->id,
                    'event_type' => $eventType,
                ]);
                return;
            }
        }

        // Increment today's execution counter
        $workflow->incrementTodayExecutions();

        // Build execution context
        $context = $this->buildContext($record, $eventType, $oldData);

        // Create execution record using domain entity
        $execution = WorkflowExecution::create(
            workflowId: $workflow->id,
            triggerType: $eventType,
            triggerRecordId: $record->id,
            triggerRecordType: ModuleRecord::class,
            contextData: $context,
        );

        // Save the execution
        $savedExecution = $this->executionRepository->save($execution);

        // Record the run if run_once_per_record is enabled
        if ($workflow->run_once_per_record) {
            $workflow->recordRunForRecord($record->id, ModuleRecord::class, $eventType);
        }

        // Dispatch the job (with optional delay)
        $delay = $workflow->delay_seconds ?? 0;

        if ($delay > 0) {
            ExecuteWorkflowJob::dispatch($savedExecution->getId(), $context)
                ->delay(now()->addSeconds($delay));
        } else {
            ExecuteWorkflowJob::dispatch($savedExecution->getId(), $context);
        }

        Log::info('Workflow triggered', [
            'workflow_id' => $workflow->id,
            'workflow_name' => $workflow->name,
            'execution_id' => $savedExecution->getId(),
            'record_id' => $record->id,
            'event_type' => $eventType,
            'delay_seconds' => $delay,
        ]);
    }

    /**
     * Build the context data for workflow execution.
     */
    protected function buildContext(ModuleRecord $record, string $eventType, ?array $oldData): array
    {
        $record->load(['module', 'owner']);

        return [
            'trigger_type' => $eventType,
            'record' => [
                'id' => $record->id,
                'module_id' => $record->module_id,
                'module_api_name' => $record->module?->api_name,
                'data' => $record->data ?? [],
                'owner_id' => $record->owner_id,
                'created_at' => $record->created_at?->toIso8601String(),
                'updated_at' => $record->updated_at?->toIso8601String(),
            ],
            'old_data' => $oldData,
            'changed_fields' => $oldData ? $this->getChangedFields($record->data ?? [], $oldData) : [],
            'triggered_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the fields that changed between old and new data.
     */
    protected function getChangedFields(array $newData, array $oldData): array
    {
        $changed = [];
        $allKeys = array_unique(array_merge(array_keys($newData), array_keys($oldData)));

        foreach ($allKeys as $key) {
            $oldValue = $oldData[$key] ?? null;
            $newValue = $newData[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changed[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changed;
    }
}