<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Domain\Blueprint\Entities\BlueprintRecordState;
use App\Domain\Blueprint\Entities\TransitionExecution as BlueprintTransitionExecution;
use App\Domain\Blueprint\Repositories\BlueprintRecordStateRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Handles the actual state transition for records.
 */
class TransitionService
{
    public function __construct(
        protected BlueprintRecordStateRepositoryInterface $recordStateRepository,
        protected ModuleRepositoryInterface $moduleRepository,
        protected ModuleRecordRepositoryInterface $recordRepository,
    ) {}

    /**
     * Execute a transition (update record state and field value).
     */
    public function execute(BlueprintTransitionExecution $execution): void
    {
        $transition = $execution->getTransition();
        $blueprint = $transition->getBlueprint();
        $field = $blueprint->getField();
        $toState = $execution->getToState();

        DB::transaction(function () use ($execution, $blueprint, $field, $toState) {
            // Update the blueprint record state
            $recordState = $this->recordStateRepository->findByBlueprintAndRecord(
                $blueprint->getId(),
                $execution->getRecordId()
            );

            if ($recordState) {
                $recordState->updateCurrentState($toState->getId(), now());
            } else {
                $recordState = new BlueprintRecordState(
                    blueprintId: $blueprint->getId(),
                    recordId: $execution->getRecordId(),
                    currentStateId: $toState->getId(),
                    stateEnteredAt: now()
                );
            }

            $this->recordStateRepository->save($recordState);

            // Update the actual field value on the record
            $this->updateRecordField(
                $blueprint->getModuleId(),
                $execution->getRecordId(),
                $field->getApiName(),
                $toState->getFieldOptionValue()
            );
        });
    }

    /**
     * Update the field value on the actual record.
     */
    protected function updateRecordField(int $moduleId, int $recordId, string $fieldName, ?string $value): void
    {
        // Get the module to determine the table name
        $module = $this->moduleRepository->findById($moduleId);
        if (!$module) {
            return;
        }

        // Update using the record repository
        $record = $this->recordRepository->findById($recordId, $moduleId);
        if (!$record) {
            return;
        }

        $record->setFieldValue($fieldName, $value);
        $this->recordRepository->save($record);
    }

    /**
     * Rollback a transition (if needed).
     */
    public function rollback(BlueprintTransitionExecution $execution): void
    {
        if (!$execution->from_state_id) {
            return;
        }

        $transition = $execution->transition;
        $blueprint = $transition->blueprint;
        $field = $blueprint->field;
        $fromState = $execution->fromState;

        DB::transaction(function () use ($execution, $blueprint, $field, $fromState) {
            // Restore the previous state
            BlueprintRecordState::updateOrCreate(
                [
                    'blueprint_id' => $blueprint->id,
                    'record_id' => $execution->record_id,
                ],
                [
                    'current_state_id' => $fromState->id,
                    'state_entered_at' => now(),
                ]
            );

            // Restore the field value
            if ($fromState) {
                $this->updateRecordField($blueprint->module_id, $execution->record_id, $field->api_name, $fromState->field_option_value);
            }

            // Mark execution as failed
            $execution->update([
                'status' => BlueprintTransitionExecution::STATUS_FAILED,
                'error_message' => 'Transition rolled back',
            ]);
        });
    }

    /**
     * Validate that a transition can be executed.
     */
    public function validateTransition(BlueprintTransitionExecution $execution): array
    {
        $errors = [];

        // Check execution status
        if (!$execution->canComplete()) {
            $errors[] = "Execution is in status '{$execution->status}' and cannot be completed";
        }

        // Check that the to_state exists
        if (!$execution->toState) {
            $errors[] = 'Target state does not exist';
        }

        // Check that the transition is still active
        $transition = $execution->transition;
        if (!$transition || !$transition->is_active) {
            $errors[] = 'Transition is not active';
        }

        // Check that the blueprint is still active
        $blueprint = $transition?->blueprint;
        if (!$blueprint || !$blueprint->is_active) {
            $errors[] = 'Blueprint is not active';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
