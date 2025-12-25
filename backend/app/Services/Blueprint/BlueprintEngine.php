<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Entities\BlueprintRecordState;
use App\Domain\Blueprint\Entities\BlueprintState;
use App\Domain\Blueprint\Entities\BlueprintTransition;
use App\Domain\Blueprint\Entities\TransitionExecution as BlueprintTransitionExecution;
use App\Domain\Blueprint\Repositories\BlueprintRepositoryInterface;
use App\Domain\Blueprint\Repositories\BlueprintRecordStateRepositoryInterface;
use App\Domain\Blueprint\Repositories\BlueprintTransitionRepositoryInterface;
use App\Domain\Blueprint\Repositories\TransitionExecutionRepositoryInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Main orchestrator for the Blueprint system.
 * Manages state transitions, requirements, approvals, and actions.
 */
class BlueprintEngine
{
    public function __construct(
        protected BlueprintRepositoryInterface $blueprintRepository,
        protected BlueprintRecordStateRepositoryInterface $recordStateRepository,
        protected BlueprintTransitionRepositoryInterface $transitionRepository,
        protected TransitionExecutionRepositoryInterface $executionRepository,
        protected ModuleRepositoryInterface $moduleRepository,
        protected ConditionService $conditionService,
        protected RequirementService $requirementService,
        protected TransitionService $transitionService,
        protected ActionService $actionService,
        protected ApprovalService $approvalService,
        protected SLAService $slaService,
    ) {}

    /**
     * Get the blueprint for a specific field.
     */
    public function getBlueprintForField(int $fieldId): ?Blueprint
    {
        return $this->blueprintRepository->findByFieldId($fieldId);
    }

    /**
     * Get the blueprint for a module and field.
     */
    public function getBlueprintForModuleField(int $moduleId, int $fieldId): ?Blueprint
    {
        return $this->blueprintRepository->findByModuleAndField($moduleId, $fieldId);
    }

    /**
     * Get all blueprints for a module.
     */
    public function getBlueprintsForModule(int $moduleId): Collection
    {
        return $this->blueprintRepository->findByModuleId($moduleId);
    }

    /**
     * Get the current state for a record in a blueprint.
     */
    public function getRecordState(int $blueprintId, int $recordId): ?BlueprintRecordState
    {
        return $this->recordStateRepository->findByBlueprintAndRecord($blueprintId, $recordId);
    }

    /**
     * Initialize a record's state based on the field's current value.
     */
    public function initializeRecordState(Blueprint $blueprint, int $recordId, ?string $currentFieldValue = null): BlueprintRecordState
    {
        // First check if state already exists
        $existingState = $this->getRecordState($blueprint->getId(), $recordId);
        if ($existingState) {
            return $existingState;
        }

        // Find the matching state based on field value
        $state = null;
        if ($currentFieldValue !== null) {
            $state = $blueprint->getStateByFieldValue($currentFieldValue);
        }

        // Fall back to initial state if no match
        if (!$state) {
            $state = $blueprint->getInitialState();
        }

        // If still no state, get the first state
        if (!$state) {
            $states = $blueprint->getStates();
            $state = $states->first();
        }

        if (!$state) {
            throw new \RuntimeException("Blueprint {$blueprint->getId()} has no states defined");
        }

        $recordState = new BlueprintRecordState(
            blueprintId: $blueprint->getId(),
            recordId: $recordId,
            currentStateId: $state->getId(),
            stateEnteredAt: now()
        );

        $recordState = $this->recordStateRepository->save($recordState);

        // Start SLA if configured
        $this->slaService->startSLA($recordId, $state);

        return $recordState;
    }

    /**
     * Get available transitions for a record from its current state.
     */
    public function getAvailableTransitions(int $blueprintId, int $recordId, array $recordData = []): Collection
    {
        $recordState = $this->getRecordState($blueprintId, $recordId);

        if (!$recordState) {
            // Initialize state if not exists
            $blueprint = $this->blueprintRepository->findById($blueprintId);
            if (!$blueprint) {
                return collect();
            }
            $field = $blueprint->getField();
            $recordState = $this->initializeRecordState($blueprint, $recordId, $recordData[$field->getApiName()] ?? null);
        }

        // Get transitions from current state
        $transitions = $this->transitionRepository->findActiveByBlueprintAndFromState(
            $blueprintId,
            $recordState->getCurrentStateId()
        );

        // Filter by conditions (before-phase)
        return $transitions->filter(function (BlueprintTransition $transition) use ($recordData) {
            return $this->conditionService->evaluate($transition, $recordData);
        });
    }

    /**
     * Start a transition (create execution record).
     */
    public function startTransition(int $recordId, int $transitionId, int $userId, array $recordData = []): BlueprintTransitionExecution
    {
        $transition = BlueprintTransition::with(['blueprint', 'fromState', 'toState', 'conditions', 'requirements', 'approval'])
            ->findOrFail($transitionId);

        // Validate current state matches from_state
        $recordState = $this->getRecordState($transition->blueprint_id, $recordId);
        if (!$recordState && $transition->from_state_id !== null) {
            throw new \RuntimeException('Record has no state and transition requires a specific from_state');
        }

        if ($recordState && $recordState->current_state_id !== $transition->from_state_id) {
            throw new \RuntimeException('Record is not in the expected state for this transition');
        }

        // Check conditions (before-phase)
        if (!$this->conditionService->evaluate($transition, $recordData)) {
            $failedConditions = $this->conditionService->getFailedConditions($transition, $recordData);
            throw new \RuntimeException('Transition conditions not met: ' . implode(', ', $failedConditions));
        }

        // Create execution record
        $execution = BlueprintTransitionExecution::create([
            'transition_id' => $transitionId,
            'record_id' => $recordId,
            'from_state_id' => $recordState?->current_state_id ?? $transition->from_state_id,
            'to_state_id' => $transition->to_state_id,
            'executed_by' => $userId,
            'status' => $transition->hasRequirements()
                ? BlueprintTransitionExecution::STATUS_PENDING_REQUIREMENTS
                : BlueprintTransitionExecution::STATUS_PENDING,
            'started_at' => now(),
        ]);

        return $execution;
    }

    /**
     * Submit requirements for a transition (during-phase).
     */
    public function submitRequirements(int $executionId, array $data): BlueprintTransitionExecution
    {
        $execution = BlueprintTransitionExecution::with(['transition.requirements', 'transition.approval'])
            ->findOrFail($executionId);

        if (!$execution->isPendingRequirements()) {
            throw new \RuntimeException('Execution is not pending requirements');
        }

        // Validate requirements
        $validation = $this->requirementService->validate($execution->transition, $data);
        if (!$validation['valid']) {
            throw new \RuntimeException('Requirements not satisfied: ' . implode(', ', $validation['errors']));
        }

        // Store requirements data
        $execution->update([
            'requirements_data' => $data,
        ]);

        // Check if approval is needed
        if ($execution->transition->requiresApproval()) {
            $this->approvalService->createApprovalRequest($execution);
            $execution->update(['status' => BlueprintTransitionExecution::STATUS_PENDING_APPROVAL]);
        } else {
            $execution->update(['status' => BlueprintTransitionExecution::STATUS_PENDING]);
        }

        return $execution->fresh();
    }

    /**
     * Complete a transition (after-phase).
     */
    public function completeTransition(int $executionId): BlueprintTransitionExecution
    {
        $execution = BlueprintTransitionExecution::with([
            'transition.blueprint.field',
            'transition.actions',
            'toState',
        ])->findOrFail($executionId);

        if (!$execution->canComplete()) {
            throw new \RuntimeException("Execution cannot be completed. Current status: {$execution->status}");
        }

        return DB::transaction(function () use ($execution) {
            // Execute the transition
            $this->transitionService->execute($execution);

            // Execute after-phase actions
            $this->actionService->executeActions($execution);

            // Complete old SLA and start new one
            $this->slaService->completeSLA($execution->record_id, $execution->transition->blueprint_id);
            $this->slaService->startSLA($execution->record_id, $execution->toState);

            // Mark execution as completed
            $execution->update([
                'status' => BlueprintTransitionExecution::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            return $execution->fresh();
        });
    }

    /**
     * Cancel a transition.
     */
    public function cancelTransition(int $executionId): void
    {
        $execution = BlueprintTransitionExecution::findOrFail($executionId);

        if ($execution->isCompleted()) {
            throw new \RuntimeException('Cannot cancel a completed transition');
        }

        $execution->update([
            'status' => BlueprintTransitionExecution::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get transition history for a record.
     */
    public function getTransitionHistory(int $blueprintId, int $recordId): Collection
    {
        $transitionIds = BlueprintTransition::where('blueprint_id', $blueprintId)
            ->pluck('id');

        return BlueprintTransitionExecution::whereIn('transition_id', $transitionIds)
            ->where('record_id', $recordId)
            ->with(['transition', 'fromState', 'toState', 'executedBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Sync states from field options.
     */
    public function syncStatesFromFieldOptions(Blueprint $blueprint): void
    {
        $field = $blueprint->field;
        if (!$field) {
            return;
        }

        $options = $field->options()->get();
        $existingStateValues = $blueprint->states()->pluck('field_option_value')->toArray();

        foreach ($options as $option) {
            if (!in_array($option->value, $existingStateValues)) {
                $blueprint->states()->create([
                    'name' => $option->label,
                    'field_option_value' => $option->value,
                    'color' => $option->metadata['color'] ?? null,
                    'is_initial' => $option->metadata['is_initial'] ?? false,
                    'is_terminal' => $option->metadata['is_terminal'] ?? ($option->metadata['is_won_stage'] ?? false) || ($option->metadata['is_lost_stage'] ?? false),
                ]);
            }
        }
    }

    /**
     * Create a default blueprint from field options.
     */
    public function createDefaultBlueprint(Module $module, int $fieldId, string $name): Blueprint
    {
        return DB::transaction(function () use ($module, $fieldId, $name) {
            $blueprint = Blueprint::create([
                'name' => $name,
                'module_id' => $module->id,
                'field_id' => $fieldId,
                'is_active' => true,
            ]);

            // Sync states from field options
            $this->syncStatesFromFieldOptions($blueprint);

            // Create default transitions (each state can go to any other state)
            $states = $blueprint->states()->get();
            foreach ($states as $fromState) {
                foreach ($states as $toState) {
                    if ($fromState->id !== $toState->id && !$fromState->is_terminal) {
                        BlueprintTransition::create([
                            'blueprint_id' => $blueprint->id,
                            'from_state_id' => $fromState->id,
                            'to_state_id' => $toState->id,
                            'name' => "Move to {$toState->name}",
                            'is_active' => true,
                        ]);
                    }
                }
            }

            return $blueprint;
        });
    }
}
