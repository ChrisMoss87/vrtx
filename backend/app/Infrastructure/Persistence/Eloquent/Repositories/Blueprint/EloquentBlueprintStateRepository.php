<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Blueprint;

use App\Domain\Blueprint\Entities\BlueprintState;
use App\Domain\Blueprint\Repositories\BlueprintStateRepositoryInterface;
use App\Models\BlueprintState as BlueprintStateModel;

class EloquentBlueprintStateRepository implements BlueprintStateRepositoryInterface
{
    public function findById(int $id): ?BlueprintState
    {
        $model = BlueprintStateModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findByBlueprintId(int $blueprintId): array
    {
        $models = BlueprintStateModel::where('blueprint_id', $blueprintId)->get();

        return $models->map(fn($m) => $this->toEntity($m))->all();
    }

    public function findInitialState(int $blueprintId): ?BlueprintState
    {
        $model = BlueprintStateModel::where('blueprint_id', $blueprintId)
            ->where('is_initial', true)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findByFieldOptionValue(int $blueprintId, string $value): ?BlueprintState
    {
        $model = BlueprintStateModel::where('blueprint_id', $blueprintId)
            ->where('field_option_value', $value)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function save(BlueprintState $state): BlueprintState
    {
        $model = $state->getId()
            ? BlueprintStateModel::find($state->getId())
            : new BlueprintStateModel();

        $model->fill([
            'blueprint_id' => $state->getBlueprintId(),
            'name' => $state->getName(),
            'field_option_value' => $state->getFieldOptionValue(),
            'color' => $state->getColor(),
            'is_initial' => $state->isInitial(),
            'is_terminal' => $state->isTerminal(),
            'position_x' => $state->getPositionX(),
            'position_y' => $state->getPositionY(),
            'metadata' => $state->getMetadata(),
        ]);

        $model->save();

        return $this->toEntity($model);
    }

    public function delete(int $id): bool
    {
        return BlueprintStateModel::destroy($id) > 0;
    }

    public function deleteByBlueprintId(int $blueprintId): int
    {
        return BlueprintStateModel::where('blueprint_id', $blueprintId)->delete();
    }

    private function toEntity(BlueprintStateModel $model): BlueprintState
    {
        return BlueprintState::reconstitute(
            id: $model->id,
            blueprintId: $model->blueprint_id,
            name: $model->name,
            fieldOptionValue: $model->field_option_value,
            color: $model->color,
            isInitial: $model->is_initial,
            isTerminal: $model->is_terminal,
            positionX: $model->position_x ?? 0,
            positionY: $model->position_y ?? 0,
            metadata: $model->metadata ?? [],
            createdAt: new \DateTimeImmutable($model->created_at),
            updatedAt: $model->updated_at ? new \DateTimeImmutable($model->updated_at) : null,
        );
    }
}
