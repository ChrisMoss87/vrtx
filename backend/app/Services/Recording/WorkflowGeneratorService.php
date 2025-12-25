<?php

namespace App\Services\Recording;


class WorkflowGeneratorService
{
    public function preview(Recording $recording): array
    {
        $steps = [];

        foreach ($recording->steps as $index => $step) {
            $workflowStepData = $step->toWorkflowStep();

            $steps[] = [
                'order' => $index + 1,
                'original_step_id' => $step->id,
                'type' => $workflowStepData['type'],
                'config' => $workflowStepData['config'],
                'description' => $step->getDescription(),
                'is_parameterized' => $step->is_parameterized,
                'action_type' => $step->action_type,
            ];
        }

        return [
            'recording_id' => $recording->id,
            'name' => $recording->name ?? 'Generated Workflow',
            'module' => $recording->module?->api_name,
            'steps' => $steps,
            'step_count' => count($steps),
            'suggested_triggers' => $this->suggestTriggers($recording),
        ];
    }

    public function generate(
        Recording $recording,
        string $name,
        string $triggerType,
        array $triggerConfig = [],
        ?string $description = null
    ): Workflow {
        // Create the workflow
        $workflow = DB::table('workflows')->insertGetId([
            'name' => $name,
            'description' => $description ?? "Generated from recording #{$recording->id}",
            'module_id' => $recording->module_id,
            'trigger_type' => $triggerType,
            'trigger_config' => $triggerConfig,
            'is_active' => false, // Start inactive so user can review
            'created_by' => $recording->user_id,
        ]);

        // Create workflow steps from recording steps
        foreach ($recording->steps as $index => $recordingStep) {
            $stepData = $recordingStep->toWorkflowStep();

            DB::table('workflow_steps')->insertGetId([
                'workflow_id' => $workflow->id,
                'step_order' => $index + 1,
                'type' => $stepData['type'],
                'name' => $recordingStep->getActionLabel(),
                'config' => $stepData['config'],
                'is_active' => true,
            ]);
        }

        return $workflow->fresh(['steps']);
    }

    public function suggestTriggers(Recording $recording): array
    {
        $triggers = [];
        $steps = $recording->steps;

        if ($steps->isEmpty()) {
            return $triggers;
        }

        // Check first step for trigger hints
        $firstStep = $steps->first();

        // If first step is a stage change, suggest stage-based trigger
        if ($firstStep->action_type === 'change_stage') {
            $data = $firstStep->action_data;
            $triggers[] = [
                'type' => 'stage_change',
                'label' => 'When stage changes to ' . ($data['new_stage'] ?? 'selected stage'),
                'config' => [
                    'stage_id' => $data['new_stage_id'] ?? null,
                ],
            ];
        }

        // If first step is field update, suggest field change trigger
        if ($firstStep->action_type === 'update_field') {
            $data = $firstStep->action_data;
            $triggers[] = [
                'type' => 'field_change',
                'label' => 'When "' . ($data['field'] ?? 'field') . '" changes',
                'config' => [
                    'field' => $data['field'] ?? null,
                    'new_value' => $data['new_value'] ?? null,
                ],
            ];
        }

        // Always suggest manual trigger
        $triggers[] = [
            'type' => 'manual',
            'label' => 'Run manually',
            'config' => [],
        ];

        // Suggest record creation trigger if module is set
        if ($recording->module_id) {
            $triggers[] = [
                'type' => 'record_created',
                'label' => 'When a new record is created',
                'config' => [],
            ];
        }

        // Suggest scheduled trigger
        $triggers[] = [
            'type' => 'scheduled',
            'label' => 'Run on schedule',
            'config' => [
                'frequency' => 'daily',
            ],
        ];

        return $triggers;
    }

    public function validateWorkflowGeneration(Recording $recording): array
    {
        $errors = [];
        $warnings = [];

        if ($recording->steps->isEmpty()) {
            $errors[] = 'Recording has no steps to convert';
        }

        // Check for non-convertible steps
        $nonConvertible = ['log_activity', 'add_note'];
        $hasNonConvertible = $recording->steps->whereIn('action_type', $nonConvertible)->isNotEmpty();

        if ($hasNonConvertible) {
            $warnings[] = 'Some steps (notes, activity logs) may have limited automation support';
        }

        // Check for unparameterized specific values
        $hasSpecificValues = $recording->steps
            ->where('is_parameterized', false)
            ->filter(function ($step) {
                $data = $step->action_data;
                return isset($data['recipient']) || isset($data['user_id']) || isset($data['assignee_id']);
            })
            ->isNotEmpty();

        if ($hasSpecificValues) {
            $warnings[] = 'Some steps contain specific values (emails, users) that may not work for all records. Consider parameterizing them.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
