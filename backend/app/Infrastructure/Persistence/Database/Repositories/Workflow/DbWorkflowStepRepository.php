<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Workflow;

use App\Domain\Workflow\Entities\WorkflowStep;
use App\Domain\Workflow\Repositories\WorkflowStepRepositoryInterface;
use App\Domain\Workflow\ValueObjects\ActionConfig;
use App\Domain\Workflow\ValueObjects\ActionType;
use App\Domain\Workflow\ValueObjects\StepConditions;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbWorkflowStepRepository implements WorkflowStepRepositoryInterface
{
    private const TABLE = 'workflow_steps';

    public function findById(int $id): ?WorkflowStep
    {
        $record = DB::table(self::TABLE)
            ->where('id', $id)
            ->first();

        if (!$record) {
            return null;
        }

        return $this->toDomainEntity($record);
    }

    public function findByWorkflowId(int $workflowId): array
    {
        $records = DB::table(self::TABLE)
            ->where('workflow_id', $workflowId)
            ->orderBy('order', 'asc')
            ->get();

        return array_map(fn($r) => $this->toDomainEntity($r), $records->all());
    }

    public function save(WorkflowStep $step): WorkflowStep
    {
        $data = $this->toModelData($step);
        $now = now()->toDateTimeString();

        if ($step->getId() !== null) {
            $data['updated_at'] = $now;

            DB::table(self::TABLE)
                ->where('id', $step->getId())
                ->update($data);

            $id = $step->getId();
        } else {
            $data['created_at'] = $now;
            $data['updated_at'] = $now;

            $id = DB::table(self::TABLE)->insertGetId($data);
        }

        $record = DB::table(self::TABLE)->where('id', $id)->first();
        return $this->toDomainEntity($record);
    }

    public function delete(int $id): bool
    {
        $record = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$record) {
            return false;
        }

        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function deleteByWorkflowId(int $workflowId): int
    {
        return DB::table(self::TABLE)->where('workflow_id', $workflowId)->delete();
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
     * Convert a database record to a domain entity.
     */
    private function toDomainEntity(stdClass $record): WorkflowStep
    {
        $actionConfig = is_string($record->action_config ?? null)
            ? json_decode($record->action_config, true)
            : $this->toArray($record->action_config);

        $conditions = is_string($record->conditions ?? null)
            ? json_decode($record->conditions, true)
            : $this->toArray($record->conditions);

        return WorkflowStep::reconstitute(
            id: $record->id,
            workflowId: $record->workflow_id,
            order: $record->order ?? 0,
            name: $record->name,
            actionType: ActionType::from($record->action_type),
            actionConfig: ActionConfig::fromArray($actionConfig ?? []),
            conditions: StepConditions::fromArray($conditions ?? []),
            branchId: $record->branch_id,
            isParallel: (bool) ($record->is_parallel ?? false),
            continueOnError: (bool) ($record->continue_on_error ?? false),
            retryCount: $record->retry_count ?? 0,
            retryDelaySeconds: $record->retry_delay_seconds ?? 60,
        );
    }

    /**
     * Convert a domain entity to database data.
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
            'action_config' => json_encode($step->actionConfig()->toArray()),
            'conditions' => json_encode($step->conditions()->toArray()),
            'branch_id' => $step->branchId(),
            'is_parallel' => $step->isParallel(),
            'continue_on_error' => $step->continueOnError(),
            'retry_count' => $step->retryCount(),
            'retry_delay_seconds' => $step->retryDelaySeconds(),
        ];
    }

    /**
     * Convert stdClass or array to array.
     */
    private function toArray(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof stdClass) {
            return json_decode(json_encode($value), true);
        }

        return null;
    }
}
