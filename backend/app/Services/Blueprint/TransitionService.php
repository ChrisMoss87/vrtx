<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Models\BlueprintRecordState;
use App\Models\BlueprintTransitionExecution;
use Illuminate\Support\Facades\DB;

/**
 * Handles the actual state transition for records.
 */
class TransitionService
{
    /**
     * Execute a transition (update record state and field value).
     */
    public function execute(BlueprintTransitionExecution $execution): void
    {
        $transition = $execution->transition;
        $blueprint = $transition->blueprint;
        $field = $blueprint->field;
        $toState = $execution->toState;

        DB::transaction(function () use ($execution, $blueprint, $field, $toState) {
            // Update the blueprint record state
            $recordState = BlueprintRecordState::updateOrCreate(
                [
                    'blueprint_id' => $blueprint->id,
                    'record_id' => $execution->record_id,
                ],
                [
                    'current_state_id' => $toState->id,
                    'state_entered_at' => now(),
                ]
            );

            // Update the actual field value on the record
            $this->updateRecordField($blueprint->module_id, $execution->record_id, $field->api_name, $toState->field_option_value);
        });
    }

    /**
     * Update the field value on the actual record.
     */
    protected function updateRecordField(int $moduleId, int $recordId, string $fieldName, ?string $value): void
    {
        // Get the module to determine the table name
        $module = \App\Models\Module::find($moduleId);
        if (!$module) {
            return;
        }

        // Get the table name (could be the module's API name or a custom table)
        $tableName = $module->api_name;

        // Check if table exists
        if (!\Illuminate\Support\Facades\Schema::hasTable($tableName)) {
            // Try with a 'records' table approach (generic records table)
            if (\Illuminate\Support\Facades\Schema::hasTable('records')) {
                DB::table('records')
                    ->where('id', $recordId)
                    ->where('module_id', $moduleId)
                    ->update([
                        'data->' . $fieldName => $value,
                        'updated_at' => now(),
                    ]);
            }
            return;
        }

        // Update the record in the module-specific table
        DB::table($tableName)
            ->where('id', $recordId)
            ->update([
                $fieldName => $value,
                'updated_at' => now(),
            ]);
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
