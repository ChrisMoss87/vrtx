<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Blueprint;

use App\Domain\Blueprint\Entities\BlueprintRecordState;
use App\Domain\Blueprint\Repositories\BlueprintRecordStateRepositoryInterface;
use App\Models\BlueprintRecordState as BlueprintRecordStateModel;

class EloquentBlueprintRecordStateRepository implements BlueprintRecordStateRepositoryInterface
{
    public function findById(int $id): ?BlueprintRecordState
    {
        $model = BlueprintRecordStateModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findByRecordId(int $blueprintId, int $recordId): ?BlueprintRecordState
    {
        $model = BlueprintRecordStateModel::where('blueprint_id', $blueprintId)
            ->where('record_id', $recordId)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findByStateId(int $stateId): array
    {
        $models = BlueprintRecordStateModel::where('current_state_id', $stateId)->get();

        return $models->map(fn($m) => $this->toEntity($m))->all();
    }

    public function findByBlueprintId(int $blueprintId): array
    {
        $models = BlueprintRecordStateModel::where('blueprint_id', $blueprintId)->get();

        return $models->map(fn($m) => $this->toEntity($m))->all();
    }

    public function save(BlueprintRecordState $recordState): BlueprintRecordState
    {
        $model = $recordState->getId()
            ? BlueprintRecordStateModel::find($recordState->getId())
            : new BlueprintRecordStateModel();

        $model->fill([
            'blueprint_id' => $recordState->getBlueprintId(),
            'record_id' => $recordState->getRecordId(),
            'current_state_id' => $recordState->getCurrentStateId(),
            'state_entered_at' => $recordState->getEnteredStateAt(),
            'sla_instance_id' => $recordState->getSlaInstanceId(),
            'metadata' => $recordState->getMetadata(),
        ]);

        $model->save();

        return $this->toEntity($model);
    }

    public function delete(int $id): bool
    {
        return BlueprintRecordStateModel::destroy($id) > 0;
    }

    public function deleteByRecordId(int $blueprintId, int $recordId): bool
    {
        return BlueprintRecordStateModel::where('blueprint_id', $blueprintId)
            ->where('record_id', $recordId)
            ->delete() > 0;
    }

    private function toEntity(BlueprintRecordStateModel $model): BlueprintRecordState
    {
        return BlueprintRecordState::reconstitute(
            id: $model->id,
            blueprintId: $model->blueprint_id,
            recordId: $model->record_id,
            currentStateId: $model->current_state_id,
            enteredStateAt: $model->state_entered_at ? new \DateTimeImmutable($model->state_entered_at) : new \DateTimeImmutable(),
            slaInstanceId: $model->sla_instance_id ?? null,
            metadata: $model->metadata ?? [],
            createdAt: new \DateTimeImmutable($model->created_at),
            updatedAt: $model->updated_at ? new \DateTimeImmutable($model->updated_at) : null,
        );
    }
}
