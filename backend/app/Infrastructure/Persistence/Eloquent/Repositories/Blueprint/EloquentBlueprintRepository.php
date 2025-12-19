<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Blueprint;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Entities\BlueprintSla;
use App\Domain\Blueprint\Entities\BlueprintState;
use App\Domain\Blueprint\Entities\BlueprintTransition;
use App\Domain\Blueprint\Repositories\BlueprintRepositoryInterface;
use App\Models\Blueprint as BlueprintModel;

class EloquentBlueprintRepository implements BlueprintRepositoryInterface
{
    public function findById(int $id): ?Blueprint
    {
        $model = BlueprintModel::with(['states', 'transitions', 'slas'])->find($id);

        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findByModuleId(int $moduleId): array
    {
        $models = BlueprintModel::with(['states', 'transitions', 'slas'])
            ->where('module_id', $moduleId)
            ->get();

        return $models->map(fn($m) => $this->toEntity($m))->all();
    }

    public function findByFieldId(int $fieldId): ?Blueprint
    {
        $model = BlueprintModel::with(['states', 'transitions', 'slas'])
            ->where('field_id', $fieldId)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findActiveForModule(int $moduleId): array
    {
        $models = BlueprintModel::with(['states', 'transitions', 'slas'])
            ->where('module_id', $moduleId)
            ->where('is_active', true)
            ->get();

        return $models->map(fn($m) => $this->toEntity($m))->all();
    }

    public function findAll(): array
    {
        $models = BlueprintModel::with(['states', 'transitions', 'slas'])->get();

        return $models->map(fn($m) => $this->toEntity($m))->all();
    }

    public function save(Blueprint $blueprint): Blueprint
    {
        $model = $blueprint->getId()
            ? BlueprintModel::find($blueprint->getId())
            : new BlueprintModel();

        $model->fill([
            'name' => $blueprint->getName(),
            'module_id' => $blueprint->getModuleId(),
            'field_id' => $blueprint->getFieldId(),
            'description' => $blueprint->getDescription(),
            'is_active' => $blueprint->isActive(),
            'layout_data' => $blueprint->getLayoutData(),
        ]);

        $model->save();

        // Reload with relationships
        $model->load(['states', 'transitions', 'slas']);

        return $this->toEntity($model);
    }

    public function delete(int $id): bool
    {
        return BlueprintModel::destroy($id) > 0;
    }

    private function toEntity(BlueprintModel $model): Blueprint
    {
        $blueprint = Blueprint::reconstitute(
            id: $model->id,
            name: $model->name,
            moduleId: $model->module_id,
            fieldId: $model->field_id,
            description: $model->description,
            isActive: $model->is_active,
            layoutData: $model->layout_data ?? [],
            createdAt: new \DateTimeImmutable($model->created_at),
            updatedAt: $model->updated_at ? new \DateTimeImmutable($model->updated_at) : null,
        );

        // Map states
        $states = $model->states->map(fn($s) => BlueprintState::reconstitute(
            id: $s->id,
            blueprintId: $s->blueprint_id,
            name: $s->name,
            fieldOptionValue: $s->field_option_value,
            color: $s->color,
            isInitial: $s->is_initial,
            isTerminal: $s->is_terminal,
            positionX: $s->position_x ?? 0,
            positionY: $s->position_y ?? 0,
            metadata: $s->metadata ?? [],
            createdAt: new \DateTimeImmutable($s->created_at),
            updatedAt: $s->updated_at ? new \DateTimeImmutable($s->updated_at) : null,
        ))->all();
        $blueprint->setStates($states);

        // Map transitions
        $transitions = $model->transitions->map(fn($t) => $this->toTransitionEntity($t))->all();
        $blueprint->setTransitions($transitions);

        // Map SLAs
        $slas = $model->slas->map(fn($s) => BlueprintSla::reconstitute(
            id: $s->id,
            blueprintId: $s->blueprint_id,
            stateId: $s->state_id,
            name: $s->name,
            durationHours: $s->duration_hours,
            warningHours: $s->warning_hours ?? 0,
            businessHoursOnly: $s->business_hours_only ?? false,
            escalationConfig: $s->escalation_config ?? [],
            isActive: $s->is_active ?? true,
            createdAt: new \DateTimeImmutable($s->created_at),
            updatedAt: $s->updated_at ? new \DateTimeImmutable($s->updated_at) : null,
        ))->all();
        $blueprint->setSlas($slas);

        return $blueprint;
    }

    private function toTransitionEntity($model): BlueprintTransition
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
