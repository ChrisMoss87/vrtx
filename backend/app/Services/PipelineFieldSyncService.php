<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Service to synchronize pipeline stages with their corresponding module field options.
 *
 * When a pipeline is created or updated, this service ensures that the stage field
 * on the module has options that match the pipeline stages.
 */
class PipelineFieldSyncService
{
    /**
     * Sync field options to match pipeline stages.
     *
     * This creates/updates/deletes field options to match the stages in the pipeline.
     * The field option value will be the stage ID, and the label will be the stage name.
     */
    public function syncFieldOptionsFromStages(Pipeline $pipeline): void
    {
        if (!$pipeline->stage_field_api_name) {
            return;
        }

        $module = $pipeline->module;
        if (!$module) {
            return;
        }

        $field = DB::table('fields')->where('module_id', $module->id)
            ->where('api_name', $pipeline->stage_field_api_name)
            ->first();

        if (!$field) {
            return;
        }

        DB::transaction(function () use ($pipeline, $field) {
            $stages = $pipeline->stages()->orderBy('display_order')->get();
            $existingOptions = DB::table('field_options')->where('field_id', $field->id)->get()->keyBy('value');
            $processedValues = [];

            foreach ($stages as $stage) {
                $stageValue = (string) $stage->id;
                $processedValues[] = $stageValue;

                if ($existingOptions->has($stageValue)) {
                    // Update existing option
                    $option = $existingOptions->get($stageValue);
                    $option->update([
                        'label' => $stage->name,
                        'display_order' => $stage->display_order,
                        'is_active' => true,
                        'metadata' => [
                            'pipeline_id' => $pipeline->id,
                            'stage_id' => $stage->id,
                            'color' => $stage->color,
                            'probability' => $stage->probability,
                            'is_won_stage' => $stage->is_won_stage,
                            'is_lost_stage' => $stage->is_lost_stage,
                        ],
                    ]);
                } else {
                    // Create new option
                    DB::table('field_options')->insertGetId([
                        'field_id' => $field->id,
                        'label' => $stage->name,
                        'value' => $stageValue,
                        'display_order' => $stage->display_order,
                        'is_active' => true,
                        'metadata' => [
                            'pipeline_id' => $pipeline->id,
                            'stage_id' => $stage->id,
                            'color' => $stage->color,
                            'probability' => $stage->probability,
                            'is_won_stage' => $stage->is_won_stage,
                            'is_lost_stage' => $stage->is_lost_stage,
                        ],
                    ]);
                }
            }

            // Deactivate options that no longer have corresponding stages
            // (but don't delete to preserve historical data)
            DB::table('field_options')->where('field_id', $field->id)
                ->whereNotIn('value', $processedValues)
                ->whereNotNull('metadata->pipeline_id')
                ->where('metadata->pipeline_id', $pipeline->id)
                ->update(['is_active' => false]);
        });
    }

    /**
     * Create or get the stage field for a pipeline.
     *
     * If the field doesn't exist, creates a new select field.
     * Returns the field that was created or found.
     */
    public function ensureStageFieldExists(Pipeline $pipeline, string $fieldApiName = 'stage'): Field
    {
        $module = $pipeline->module;

        $field = DB::table('fields')->where('module_id', $module->id)
            ->where('api_name', $fieldApiName)
            ->first();

        if ($field) {
            return $field;
        }

        // Create the stage field
        $maxOrder = DB::table('fields')->where('module_id', $module->id)->max('display_order') ?? 0;

        return DB::table('fields')->insertGetId([
            'module_id' => $module->id,
            'name' => 'Stage',
            'api_name' => $fieldApiName,
            'type' => 'select',
            'display_order' => $maxOrder + 1,
            'is_required' => false,
            'is_unique' => false,
            'is_searchable' => true,
            'is_sortable' => true,
            'is_filterable' => true,
            'show_in_list' => true,
            'show_in_detail' => true,
            'show_in_create' => true,
            'show_in_edit' => true,
            'settings' => [
                'pipeline_controlled' => true,
                'pipeline_id' => $pipeline->id,
            ],
        ]);
    }

    /**
     * Get the default (first) stage for a pipeline.
     */
    public function getDefaultStage(Pipeline $pipeline): ?Stage
    {
        return $pipeline->stages()->orderBy('display_order')->first();
    }

    /**
     * Set initial stage for a new record.
     */
    public function setInitialStage(array &$recordData, Pipeline $pipeline): void
    {
        if (!$pipeline->stage_field_api_name) {
            return;
        }

        $defaultStage = $this->getDefaultStage($pipeline);
        if ($defaultStage) {
            $recordData[$pipeline->stage_field_api_name] = (string) $defaultStage->id;
        }
    }

    /**
     * Validate that a stage value is valid for the pipeline.
     */
    public function isValidStage(Pipeline $pipeline, $stageValue): bool
    {
        if ($stageValue === null) {
            return true; // Allow null stages
        }

        return $pipeline->stages()->where('id', (int) $stageValue)->exists();
    }

    /**
     * Get stage info from a record's stage value.
     */
    public function getStageFromRecord(Pipeline $pipeline, array $recordData): ?Stage
    {
        if (!$pipeline->stage_field_api_name) {
            return null;
        }

        $stageValue = $recordData[$pipeline->stage_field_api_name] ?? null;
        if ($stageValue === null) {
            return null;
        }

        return Stage::find((int) $stageValue);
    }
}
