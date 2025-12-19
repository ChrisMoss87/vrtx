<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Blueprint;

use App\Domain\Blueprint\Entities\TransitionExecution;
use App\Domain\Blueprint\Repositories\TransitionExecutionRepositoryInterface;
use App\Domain\Blueprint\ValueObjects\ExecutionStatus;
use App\Domain\Shared\ValueObjects\UserId;
use App\Models\BlueprintTransitionExecution as TransitionExecutionModel;

class EloquentTransitionExecutionRepository implements TransitionExecutionRepositoryInterface
{
    public function findById(int $id): ?TransitionExecution
    {
        $model = TransitionExecutionModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findByRecordId(int $recordId): array
    {
        $models = TransitionExecutionModel::where('record_id', $recordId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toEntity($m))->all();
    }

    public function findByTransitionId(int $transitionId): array
    {
        $models = TransitionExecutionModel::where('transition_id', $transitionId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toEntity($m))->all();
    }

    public function findPendingForRecord(int $recordId): ?TransitionExecution
    {
        $model = TransitionExecutionModel::where('record_id', $recordId)
            ->whereIn('status', [
                TransitionExecutionModel::STATUS_PENDING,
                TransitionExecutionModel::STATUS_PENDING_REQUIREMENTS,
                TransitionExecutionModel::STATUS_PENDING_APPROVAL,
            ])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function save(TransitionExecution $execution): TransitionExecution
    {
        $model = $execution->getId()
            ? TransitionExecutionModel::find($execution->getId())
            : new TransitionExecutionModel();

        $model->fill([
            'transition_id' => $execution->getTransitionId(),
            'record_id' => $execution->getRecordId(),
            'from_state_id' => $execution->getFromStateId(),
            'to_state_id' => $execution->getToStateId(),
            'executed_by' => $execution->getExecutedBy()?->value(),
            'status' => $this->mapExecutionStatusToModel($execution->getStatus()),
            'requirements_data' => $execution->getRequirementData(),
            'action_results' => $execution->getActionResults(),
            'error_message' => $execution->getErrorMessage(),
            'started_at' => $execution->getStartedAt(),
            'completed_at' => $execution->getCompletedAt(),
        ]);

        $model->save();

        return $this->toEntity($model);
    }

    public function delete(int $id): bool
    {
        return TransitionExecutionModel::destroy($id) > 0;
    }

    private function toEntity(TransitionExecutionModel $model): TransitionExecution
    {
        return TransitionExecution::reconstitute(
            id: $model->id,
            transitionId: $model->transition_id,
            recordId: $model->record_id,
            fromStateId: $model->from_state_id,
            toStateId: $model->to_state_id,
            status: $this->mapModelStatusToExecutionStatus($model->status),
            executedBy: $model->executed_by ? UserId::fromInt($model->executed_by) : null,
            requirementData: $model->requirements_data ?? [],
            actionResults: $model->action_results ?? [],
            errorMessage: $model->error_message,
            startedAt: $model->started_at ? new \DateTimeImmutable($model->started_at) : null,
            completedAt: $model->completed_at ? new \DateTimeImmutable($model->completed_at) : null,
            createdAt: new \DateTimeImmutable($model->created_at),
            updatedAt: $model->updated_at ? new \DateTimeImmutable($model->updated_at) : null,
        );
    }

    private function mapModelStatusToExecutionStatus(string $status): ExecutionStatus
    {
        return match ($status) {
            TransitionExecutionModel::STATUS_PENDING => ExecutionStatus::PENDING,
            TransitionExecutionModel::STATUS_PENDING_REQUIREMENTS => ExecutionStatus::AWAITING_REQUIREMENTS,
            TransitionExecutionModel::STATUS_PENDING_APPROVAL => ExecutionStatus::AWAITING_APPROVAL,
            TransitionExecutionModel::STATUS_APPROVED => ExecutionStatus::IN_PROGRESS,
            TransitionExecutionModel::STATUS_COMPLETED => ExecutionStatus::COMPLETED,
            TransitionExecutionModel::STATUS_FAILED => ExecutionStatus::FAILED,
            TransitionExecutionModel::STATUS_CANCELLED => ExecutionStatus::CANCELLED,
            TransitionExecutionModel::STATUS_REJECTED => ExecutionStatus::CANCELLED,
            default => ExecutionStatus::PENDING,
        };
    }

    private function mapExecutionStatusToModel(ExecutionStatus $status): string
    {
        return match ($status) {
            ExecutionStatus::PENDING => TransitionExecutionModel::STATUS_PENDING,
            ExecutionStatus::AWAITING_REQUIREMENTS => TransitionExecutionModel::STATUS_PENDING_REQUIREMENTS,
            ExecutionStatus::AWAITING_APPROVAL => TransitionExecutionModel::STATUS_PENDING_APPROVAL,
            ExecutionStatus::IN_PROGRESS => TransitionExecutionModel::STATUS_APPROVED,
            ExecutionStatus::COMPLETED => TransitionExecutionModel::STATUS_COMPLETED,
            ExecutionStatus::FAILED => TransitionExecutionModel::STATUS_FAILED,
            ExecutionStatus::CANCELLED => TransitionExecutionModel::STATUS_CANCELLED,
            ExecutionStatus::ROLLED_BACK => TransitionExecutionModel::STATUS_CANCELLED,
        };
    }
}
