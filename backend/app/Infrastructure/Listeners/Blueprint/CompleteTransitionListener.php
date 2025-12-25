<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Blueprint;

use App\Domain\Blueprint\Events\AllApprovalsCompleted;
use App\Domain\Blueprint\Repositories\BlueprintRecordStateRepositoryInterface;
use App\Domain\Blueprint\Repositories\TransitionExecutionRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Services\Blueprint\ActionService;
use App\Services\Blueprint\SLAService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Completes the transition when all approvals are done.
 * Executes after-transition actions and updates the record state.
 */
class CompleteTransitionListener
{
    private const STATUS_APPROVED = 'approved';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_FAILED = 'failed';

    public function __construct(
        private readonly ActionService $actionService,
        private readonly SLAService $slaService,
        private readonly TransitionExecutionRepositoryInterface $transitionExecutionRepository,
        private readonly BlueprintRecordStateRepositoryInterface $blueprintRecordStateRepository,
        private readonly ModuleRecordRepositoryInterface $moduleRecordRepository,
    ) {}

    public function handle(AllApprovalsCompleted $event): void
    {
        $execution = $this->transitionExecutionRepository->findById($event->executionId());

        if (!$execution) {
            Log::warning('Execution not found for transition completion', [
                'execution_id' => $event->executionId(),
            ]);
            return;
        }

        try {
            // Mark execution as approved
            DB::table('blueprint_transition_executions')
                ->where('id', $event->executionId())
                ->update(['status' => self::STATUS_APPROVED]);

            // Execute after-transition actions
            $actionResults = $this->executeAfterActions($event->executionId());

            // Update the record's state
            $this->updateRecordState($event);

            // Get transition and blueprint details for SLA
            $transition = DB::table('blueprint_transitions')
                ->where('id', $event->transitionId())
                ->first();

            if ($transition) {
                $this->slaService->completeSLA($event->recordId(), (int) $transition->blueprint_id);

                // Start SLA for new state if applicable
                $toState = DB::table('blueprint_states')
                    ->where('id', $event->toStateId())
                    ->first();

                if ($toState) {
                    $sla = DB::table('blueprint_slas')
                        ->where('state_id', $toState->id)
                        ->where('is_active', true)
                        ->first();

                    if ($sla) {
                        $this->slaService->startSLA($event->recordId(), $toState);
                    }
                }
            }

            // Mark execution as completed
            DB::table('blueprint_transition_executions')
                ->where('id', $event->executionId())
                ->update([
                    'status' => self::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'action_results' => json_encode($actionResults),
                ]);

            Log::info('Blueprint transition completed after approval', [
                'execution_id' => $event->executionId(),
                'record_id' => $event->recordId(),
                'from_state_id' => $event->fromStateId(),
                'to_state_id' => $event->toStateId(),
            ]);

        } catch (Throwable $e) {
            Log::error('Failed to complete transition after approval', [
                'execution_id' => $event->executionId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            DB::table('blueprint_transition_executions')
                ->where('id', $event->executionId())
                ->update([
                    'status' => self::STATUS_FAILED,
                    'completed_at' => now(),
                    'error_message' => $e->getMessage(),
                ]);
        }
    }

    private function executeAfterActions(int $executionId): array
    {
        $results = [];

        // Get the execution to find transition
        $execution = $this->transitionExecutionRepository->findById($executionId);
        if (!$execution) {
            return $results;
        }

        // Get actions for this transition
        $actions = DB::table('blueprint_transition_actions')
            ->where('transition_id', $execution->getTransitionId())
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
                    'execution_id' => $executionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    private function updateRecordState(AllApprovalsCompleted $event): void
    {
        // Get transition details
        $transition = DB::table('blueprint_transitions')
            ->where('id', $event->transitionId())
            ->first();

        if (!$transition) {
            return;
        }

        // Update or create the record state using direct DB query
        $existingState = DB::table('blueprint_record_states')
            ->where('blueprint_id', $event->blueprintId())
            ->where('record_id', $event->recordId())
            ->first();

        $stateData = [
            'current_state_id' => $event->toStateId(),
            'entered_at' => now(),
            'last_transition_id' => $event->transitionId(),
            'last_transition_at' => now(),
            'updated_at' => now(),
        ];

        if ($existingState) {
            DB::table('blueprint_record_states')
                ->where('id', $existingState->id)
                ->update($stateData);
        } else {
            DB::table('blueprint_record_states')->insert(array_merge($stateData, [
                'blueprint_id' => $event->blueprintId(),
                'record_id' => $event->recordId(),
                'created_at' => now(),
            ]));
        }

        // Update the actual record field if blueprint is bound to a field
        $blueprint = DB::table('blueprints')
            ->where('id', $event->blueprintId())
            ->first();

        if ($blueprint && $blueprint->field_id) {
            $this->updateRecordField($event->recordId(), $blueprint, $event->toStateId());
        }
    }

    private function updateRecordField(int $recordId, $blueprint, int $toStateId): void
    {
        try {
            // Get the module record from database
            $recordData = DB::table('module_records')
                ->where('id', $recordId)
                ->first();

            if (!$recordData) {
                return;
            }

            // Get the field details
            $field = DB::table('fields')
                ->where('id', $blueprint->field_id)
                ->first();

            if (!$field) {
                return;
            }

            // Get the state name
            $toState = DB::table('blueprint_states')
                ->where('id', $toStateId)
                ->first();

            if (!$toState) {
                return;
            }

            // Update the record data
            $data = is_string($recordData->data)
                ? json_decode($recordData->data, true)
                : (array) $recordData->data;
            $data[$field->api_name] = $toState->name;

            DB::table('module_records')
                ->where('id', $recordId)
                ->update([
                    'data' => json_encode($data),
                    'updated_at' => now(),
                ]);

        } catch (Throwable $e) {
            Log::warning('Failed to update record field after approval', [
                'record_id' => $recordId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
