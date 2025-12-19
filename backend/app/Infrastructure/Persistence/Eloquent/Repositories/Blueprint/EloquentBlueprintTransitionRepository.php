<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Blueprint;

use App\Domain\Blueprint\Entities\BlueprintTransition;
use App\Domain\Blueprint\Repositories\BlueprintTransitionRepositoryInterface;
use App\Models\BlueprintTransition as BlueprintTransitionModel;

class EloquentBlueprintTransitionRepository implements BlueprintTransitionRepositoryInterface
{
    public function findById(int $id): ?BlueprintTransition
    {
        $model = BlueprintTransitionModel::with(['conditions', 'requirements', 'actions', 'approval'])
            ->find($id);

        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findByBlueprintId(int $blueprintId): array
    {
        $models = BlueprintTransitionModel::with(['conditions', 'requirements', 'actions', 'approval'])
            ->where('blueprint_id', $blueprintId)
            ->orderBy('display_order')
            ->get();

        return $models->map(fn($m) => $this->toEntity($m))->all();
    }

    public function findFromState(int $blueprintId, ?int $fromStateId): array
    {
        $query = BlueprintTransitionModel::with(['conditions', 'requirements', 'actions', 'approval'])
            ->where('blueprint_id', $blueprintId);

        if ($fromStateId === null) {
            $query->whereNull('from_state_id');
        } else {
            $query->where('from_state_id', $fromStateId);
        }

        $models = $query->orderBy('display_order')->get();

        return $models->map(fn($m) => $this->toEntity($m))->all();
    }

    public function findActiveFromState(int $blueprintId, ?int $fromStateId): array
    {
        $query = BlueprintTransitionModel::with(['conditions', 'requirements', 'actions', 'approval'])
            ->where('blueprint_id', $blueprintId)
            ->where('is_active', true);

        if ($fromStateId === null) {
            $query->whereNull('from_state_id');
        } else {
            $query->where('from_state_id', $fromStateId);
        }

        $models = $query->orderBy('display_order')->get();

        return $models->map(fn($m) => $this->toEntity($m))->all();
    }

    public function save(BlueprintTransition $transition): BlueprintTransition
    {
        $model = $transition->getId()
            ? BlueprintTransitionModel::find($transition->getId())
            : new BlueprintTransitionModel();

        $model->fill([
            'blueprint_id' => $transition->getBlueprintId(),
            'from_state_id' => $transition->getFromStateId(),
            'to_state_id' => $transition->getToStateId(),
            'name' => $transition->getName(),
            'description' => $transition->getDescription(),
            'button_label' => $transition->getButtonLabel(),
            'display_order' => $transition->getDisplayOrder(),
            'is_active' => $transition->isActive(),
        ]);

        $model->save();

        // Reload with relationships
        $model->load(['conditions', 'requirements', 'actions', 'approval']);

        return $this->toEntity($model);
    }

    public function delete(int $id): bool
    {
        return BlueprintTransitionModel::destroy($id) > 0;
    }

    public function deleteByBlueprintId(int $blueprintId): int
    {
        return BlueprintTransitionModel::where('blueprint_id', $blueprintId)->delete();
    }

    private function toEntity(BlueprintTransitionModel $model): BlueprintTransition
    {
        $transition = BlueprintTransition::reconstitute(
            id: $model->id,
            blueprintId: $model->blueprint_id,
            fromStateId: $model->from_state_id,
            toStateId: $model->to_state_id,
            name: $model->name,
            description: $model->description,
            buttonLabel: $model->button_label,
            displayOrder: $model->display_order ?? 0,
            isActive: $model->is_active ?? true,
            createdAt: new \DateTimeImmutable($model->created_at),
            updatedAt: $model->updated_at ? new \DateTimeImmutable($model->updated_at) : null,
        );

        // Load conditions from relationship if exists
        if ($model->relationLoaded('conditions')) {
            $conditions = $model->conditions->map(fn($c) => [
                'field_id' => $c->field_id,
                'operator' => $c->operator,
                'value' => $c->value,
            ])->all();
            $transition->setConditions($conditions);
        }

        // Load requirements from relationship if exists
        if ($model->relationLoaded('requirements')) {
            $requirements = $model->requirements->map(fn($r) => [
                'type' => $r->type,
                'field_id' => $r->field_id,
                'is_required' => $r->is_required,
                'config' => $r->config,
            ])->all();
            $transition->setRequirements($requirements);
        }

        // Load actions from relationship if exists
        if ($model->relationLoaded('actions')) {
            $actions = $model->actions->map(fn($a) => [
                'type' => $a->action_type,
                'config' => $a->action_config,
            ])->all();
            $transition->setActions($actions);
        }

        // Load approval config if exists
        if ($model->relationLoaded('approval') && $model->approval) {
            $transition->setApprovalConfig([
                'type' => $model->approval->approval_type,
                'approvers' => $model->approval->approvers,
                'config' => $model->approval->config,
            ]);
        }

        return $transition;
    }
}
