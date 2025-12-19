<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Workflow;

use App\Domain\Workflow\Entities\WorkflowStep;
use App\Domain\Workflow\Repositories\WorkflowStepRepositoryInterface;
use App\Domain\Workflow\ValueObjects\ActionConfig;
use App\Domain\Workflow\ValueObjects\ActionType;
use App\Domain\Workflow\ValueObjects\StepConditions;
use App\Models\WorkflowStep as WorkflowStepModel;

class EloquentWorkflowStepRepository implements WorkflowStepRepositoryInterface
{
    public function findById(int $id): ?WorkflowStep
    {
        $model = WorkflowStepModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByWorkflowId(int $workflowId): array
    {
        $models = WorkflowStepModel::where('workflow_id', $workflowId)
            ->orderBy('order', 'asc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(WorkflowStep $step): WorkflowStep
    {
        $data = $this->toModelData($step);

        if ($step->getId() !== null) {
            $model = WorkflowStepModel::findOrFail($step->getId());
            $model->update($data);
        } else {
            $model = WorkflowStepModel::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    public function delete(int $id): bool
    {
        $model = WorkflowStepModel::find($id);

        if (!$model) {
            return false;
        }

        return $model->delete() ?? false;
    }

    public function deleteByWorkflowId(int $workflowId): int
    {
        return WorkflowStepModel::where('workflow_id', $workflowId)->delete();
    }

    public function saveMany(int $workflowId, array $steps): array
    {
        // Delete existing steps
        $this->deleteByWorkflowId($workflowId);

        $savedSteps = [];

        // Create new steps
        foreach ($steps as $step) {
            // Ensure step is assigned to workflow
            if ($step->workflowId() !== $workflowId) {
                $step->assignToWorkflow($workflowId);
            }

            $savedSteps[] = $this->save($step);
        }

        return $savedSteps;
    }

    /**
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(WorkflowStepModel $model): WorkflowStep
    {
        return WorkflowStep::reconstitute(
            id: $model->id,
            workflowId: $model->workflow_id,
            order: $model->order ?? 0,
            name: $model->name,
            actionType: ActionType::from($model->action_type),
            actionConfig: ActionConfig::fromArray($model->action_config ?? []),
            conditions: StepConditions::fromArray($model->conditions ?? []),
            branchId: $model->branch_id,
            isParallel: $model->is_parallel ?? false,
            continueOnError: $model->continue_on_error ?? false,
            retryCount: $model->retry_count ?? 0,
            retryDelaySeconds: $model->retry_delay_seconds ?? 60,
        );
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(WorkflowStep $step): array
    {
        return [
            'workflow_id' => $step->workflowId(),
            'order' => $step->order(),
            'name' => $step->name(),
            'action_type' => $step->actionType()->value,
            'action_config' => $step->actionConfig()->toArray(),
            'conditions' => $step->conditions()->toArray(),
            'branch_id' => $step->branchId(),
            'is_parallel' => $step->isParallel(),
            'continue_on_error' => $step->continueOnError(),
            'retry_count' => $step->retryCount(),
            'retry_delay_seconds' => $step->retryDelaySeconds(),
        ];
    }
}
