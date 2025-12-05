<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use App\Models\ModuleRecord;
use App\Models\Pipeline;
use App\Models\StageHistory;

class MoveStageAction implements ActionInterface
{
    public function execute(array $config, array $context): array
    {
        $recordId = $context['record']['id'] ?? null;
        $pipelineId = $config['pipeline_id'] ?? null;
        $stageId = $config['stage_id'] ?? null;

        if (!$recordId || !$pipelineId || !$stageId) {
            throw new \InvalidArgumentException('Record ID, Pipeline ID, and Stage ID are required');
        }

        $record = ModuleRecord::find($recordId);
        $pipeline = Pipeline::find($pipelineId);

        if (!$record || !$pipeline) {
            throw new \InvalidArgumentException('Record or Pipeline not found');
        }

        $stageField = $pipeline->stage_field_api_name ?? 'stage_id';
        $oldStageId = $record->data[$stageField] ?? null;

        // Update record
        $data = $record->data;
        $data[$stageField] = (string) $stageId;
        $record->data = $data;
        $record->save();

        // Record history
        if (class_exists(StageHistory::class)) {
            StageHistory::recordTransition(
                recordId: $recordId,
                pipelineId: $pipelineId,
                fromStageId: $oldStageId ? (int) $oldStageId : null,
                toStageId: (int) $stageId,
                userId: $context['user_id'] ?? 0,
                reason: 'Moved by workflow'
            );
        }

        return ['moved' => true, 'from_stage' => $oldStageId, 'to_stage' => $stageId];
    }

    public static function getConfigSchema(): array
    {
        return ['fields' => [
            ['name' => 'pipeline_id', 'label' => 'Pipeline', 'type' => 'pipeline_select', 'required' => true],
            ['name' => 'stage_id', 'label' => 'Move to Stage', 'type' => 'stage_select', 'required' => true, 'depends_on' => 'pipeline_id'],
        ]];
    }

    public function validate(array $config): array
    {
        $errors = [];
        if (empty($config['pipeline_id'])) $errors['pipeline_id'] = 'Pipeline is required';
        if (empty($config['stage_id'])) $errors['stage_id'] = 'Stage is required';
        return $errors;
    }
}
