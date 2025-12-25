<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Blueprint;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Entities\BlueprintSla;
use App\Domain\Blueprint\Entities\BlueprintState;
use App\Domain\Blueprint\Entities\BlueprintTransition;
use App\Domain\Blueprint\Repositories\BlueprintRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbBlueprintRepository implements BlueprintRepositoryInterface
{
    private const TABLE = 'blueprints';
    private const TABLE_STATES = 'blueprint_states';
    private const TABLE_TRANSITIONS = 'blueprint_transitions';
    private const TABLE_SLAS = 'blueprint_slas';
    private const TABLE_TRANSITION_CONDITIONS = 'blueprint_transition_conditions';
    private const TABLE_TRANSITION_REQUIREMENTS = 'blueprint_transition_requirements';
    private const TABLE_TRANSITION_ACTIONS = 'blueprint_transition_actions';
    private const TABLE_TRANSITION_APPROVALS = 'blueprint_transition_approvals';

    public function findById(int $id): ?Blueprint
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntityWithRelations($row);
    }

    public function findByModuleId(int $moduleId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function findByFieldId(int $fieldId): ?Blueprint
    {
        $row = DB::table(self::TABLE)
            ->where('field_id', $fieldId)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntityWithRelations($row);
    }

    public function findActiveForModule(int $moduleId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('is_active', true)
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function findAll(): array
    {
        $rows = DB::table(self::TABLE)->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function save(Blueprint $blueprint): Blueprint
    {
        $data = $this->toRowData($blueprint);

        if ($blueprint->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $blueprint->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $blueprint->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    private function toDomainEntityWithRelations(stdClass $row): Blueprint
    {
        $blueprint = Blueprint::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            moduleId: (int) $row->module_id,
            fieldId: $row->field_id ? (int) $row->field_id : null,
            description: $row->description,
            isActive: (bool) $row->is_active,
            layoutData: $row->layout_data ? (is_string($row->layout_data) ? json_decode($row->layout_data, true) : $row->layout_data) : [],
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );

        // Load states
        $stateRows = DB::table(self::TABLE_STATES)
            ->where('blueprint_id', $row->id)
            ->get();

        $states = $stateRows->map(fn($s) => BlueprintState::reconstitute(
            id: (int) $s->id,
            blueprintId: (int) $s->blueprint_id,
            name: $s->name,
            fieldOptionValue: $s->field_option_value,
            color: $s->color,
            isInitial: (bool) $s->is_initial,
            isTerminal: (bool) $s->is_terminal,
            positionX: (int) ($s->position_x ?? 0),
            positionY: (int) ($s->position_y ?? 0),
            metadata: $s->metadata ? (is_string($s->metadata) ? json_decode($s->metadata, true) : $s->metadata) : [],
            createdAt: new DateTimeImmutable($s->created_at),
            updatedAt: $s->updated_at ? new DateTimeImmutable($s->updated_at) : null,
        ))->all();
        $blueprint->setStates($states);

        // Load transitions with their relations
        $transitionRows = DB::table(self::TABLE_TRANSITIONS)
            ->where('blueprint_id', $row->id)
            ->orderBy('display_order')
            ->get();

        $transitions = $transitionRows->map(fn($t) => $this->toTransitionEntity($t))->all();
        $blueprint->setTransitions($transitions);

        // Load SLAs
        $slaRows = DB::table(self::TABLE_SLAS)
            ->where('blueprint_id', $row->id)
            ->get();

        $slas = $slaRows->map(fn($s) => BlueprintSla::reconstitute(
            id: (int) $s->id,
            blueprintId: (int) $s->blueprint_id,
            stateId: $s->state_id ? (int) $s->state_id : null,
            name: $s->name,
            durationHours: (int) $s->duration_hours,
            warningHours: (int) ($s->warning_hours ?? 0),
            businessHoursOnly: (bool) ($s->business_hours_only ?? false),
            escalationConfig: $s->escalation_config ? (is_string($s->escalation_config) ? json_decode($s->escalation_config, true) : $s->escalation_config) : [],
            isActive: (bool) ($s->is_active ?? true),
            createdAt: new DateTimeImmutable($s->created_at),
            updatedAt: $s->updated_at ? new DateTimeImmutable($s->updated_at) : null,
        ))->all();
        $blueprint->setSlas($slas);

        return $blueprint;
    }

    private function toTransitionEntity(stdClass $row): BlueprintTransition
    {
        $transition = BlueprintTransition::reconstitute(
            id: (int) $row->id,
            blueprintId: (int) $row->blueprint_id,
            fromStateId: $row->from_state_id ? (int) $row->from_state_id : null,
            toStateId: $row->to_state_id ? (int) $row->to_state_id : null,
            name: $row->name,
            description: $row->description,
            buttonLabel: $row->button_label,
            displayOrder: (int) ($row->display_order ?? 0),
            isActive: (bool) ($row->is_active ?? true),
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );

        // Load conditions
        $conditions = DB::table(self::TABLE_TRANSITION_CONDITIONS)
            ->where('transition_id', $row->id)
            ->get()
            ->map(fn($c) => [
                'field_id' => $c->field_id,
                'operator' => $c->operator,
                'value' => $c->value,
            ])
            ->all();
        $transition->setConditions($conditions);

        // Load requirements
        $requirements = DB::table(self::TABLE_TRANSITION_REQUIREMENTS)
            ->where('transition_id', $row->id)
            ->get()
            ->map(fn($r) => [
                'type' => $r->type,
                'field_id' => $r->field_id,
                'is_required' => (bool) $r->is_required,
                'config' => $r->config ? (is_string($r->config) ? json_decode($r->config, true) : $r->config) : [],
            ])
            ->all();
        $transition->setRequirements($requirements);

        // Load actions
        $actions = DB::table(self::TABLE_TRANSITION_ACTIONS)
            ->where('transition_id', $row->id)
            ->get()
            ->map(fn($a) => [
                'type' => $a->action_type,
                'config' => $a->action_config ? (is_string($a->action_config) ? json_decode($a->action_config, true) : $a->action_config) : [],
            ])
            ->all();
        $transition->setActions($actions);

        // Load approval config
        $approval = DB::table(self::TABLE_TRANSITION_APPROVALS)
            ->where('transition_id', $row->id)
            ->first();

        if ($approval) {
            $transition->setApprovalConfig([
                'type' => $approval->approval_type,
                'approvers' => $approval->approvers ? (is_string($approval->approvers) ? json_decode($approval->approvers, true) : $approval->approvers) : [],
                'config' => $approval->config ? (is_string($approval->config) ? json_decode($approval->config, true) : $approval->config) : [],
            ]);
        }

        return $transition;
    }

    private function toRowData(Blueprint $blueprint): array
    {
        return [
            'name' => $blueprint->getName(),
            'module_id' => $blueprint->getModuleId(),
            'field_id' => $blueprint->getFieldId(),
            'description' => $blueprint->getDescription(),
            'is_active' => $blueprint->isActive(),
            'layout_data' => json_encode($blueprint->getLayoutData()),
        ];
    }
}
