<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Blueprint;

use App\Domain\Blueprint\Events\AllApprovalsCompleted;
use App\Models\BlueprintRecordState;
use App\Models\BlueprintTransitionExecution;
use App\Models\ModuleRecord;
use App\Services\Blueprint\ActionService;
use App\Services\Blueprint\SLAService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Completes the transition when all approvals are done.
 * Executes after-transition actions and updates the record state.
 */
class CompleteTransitionListener
{
    public function __construct(
        private readonly ActionService $actionService,
        private readonly SLAService $slaService,
    ) {}

    public function handle(AllApprovalsCompleted $event): void
    {
        $execution = BlueprintTransitionExecution::with([
            'transition.blueprint',
            'transition.toState',
            'transition.actions',
        ])->find($event->executionId());

        if (!$execution) {
            Log::warning('Execution not found for transition completion', [
                'execution_id' => $event->executionId(),
            ]);
            return;
        }

        try {
            // Mark execution as approved
            $execution->update([
                'status' => BlueprintTransitionExecution::STATUS_APPROVED,
            ]);

            // Execute after-transition actions
            $actionResults = $this->executeAfterActions($execution);

            // Update the record's state
            $this->updateRecordState($execution);

            // Complete SLA for the old state and start new one
            $transition = $execution->transition;
            $blueprint = $transition->blueprint;

            $this->slaService->completeSLA($execution->record_id, $blueprint->id);

            // Start SLA for new state if applicable
            $toState = $transition->toState;
            if ($toState && $toState->sla && $toState->sla->is_active) {
                $this->slaService->startSLA($execution->record_id, $toState);
            }

            // Mark execution as completed
            $execution->update([
                'status' => BlueprintTransitionExecution::STATUS_COMPLETED,
                'completed_at' => now(),
                'action_results' => $actionResults,
            ]);

            Log::info('Blueprint transition completed after approval', [
                'execution_id' => $execution->id,
                'record_id' => $execution->record_id,
                'from_state_id' => $execution->from_state_id,
                'to_state_id' => $event->toStateId(),
            ]);

        } catch (Throwable $e) {
            Log::error('Failed to complete transition after approval', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $execution->update([
                'status' => BlueprintTransitionExecution::STATUS_FAILED,
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function executeAfterActions(BlueprintTransitionExecution $execution): array
    {
        $results = [];
        $actions = $execution->transition->actions()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        foreach ($actions as $action) {
            try {
                $log = $this->actionService->executeAction($execution, $action);
                $results[$action->id] = [
                    'status' => $log->status,
                    'result' => $log->result,
                ];
            } catch (Throwable $e) {
                $results[$action->id] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];

                Log::warning('Blueprint transition action failed', [
                    'action_id' => $action->id,
                    'execution_id' => $execution->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    private function updateRecordState(BlueprintTransitionExecution $execution): void
    {
        $blueprint = $execution->transition->blueprint;
        $toState = $execution->transition->toState;

        // Update or create the record state
        BlueprintRecordState::updateOrCreate(
            [
                'blueprint_id' => $blueprint->id,
                'record_id' => $execution->record_id,
            ],
            [
                'current_state_id' => $toState->id,
                'entered_at' => now(),
                'last_transition_id' => $execution->transition_id,
                'last_transition_at' => now(),
            ]
        );

        // Update the actual record field if blueprint is bound to a field
        if ($blueprint->field_id) {
            $this->updateRecordField($execution->record_id, $blueprint, $toState);
        }
    }

    private function updateRecordField(int $recordId, $blueprint, $toState): void
    {
        try {
            $record = ModuleRecord::find($recordId);
            if (!$record) {
                return;
            }

            $field = $blueprint->field;
            if (!$field) {
                return;
            }

            // Update the record data
            $data = $record->data ?? [];
            $data[$field->api_name] = $toState->name;
            $record->update(['data' => $data]);

        } catch (Throwable $e) {
            Log::warning('Failed to update record field after approval', [
                'record_id' => $recordId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
