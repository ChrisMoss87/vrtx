<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Blueprint;

use App\Domain\Blueprint\Entities\BlueprintTransition;
use App\Domain\Blueprint\Repositories\BlueprintTransitionRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbBlueprintTransitionRepository implements BlueprintTransitionRepositoryInterface
{
    private const TABLE = 'blueprint_transitions';
    private const TABLE_CONDITIONS = 'blueprint_transition_conditions';
    private const TABLE_REQUIREMENTS = 'blueprint_transition_requirements';
    private const TABLE_ACTIONS = 'blueprint_transition_actions';
    private const TABLE_APPROVALS = 'blueprint_transition_approvals';

    public function findById(int $id): ?BlueprintTransition
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntityWithRelations($row);
    }

    public function findByBlueprintId(int $blueprintId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->orderBy('display_order')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function findFromState(int $blueprintId, ?int $fromStateId): array
    {
        $query = DB::table(self::TABLE)->where('blueprint_id', $blueprintId);

        if ($fromStateId === null) {
            $query->whereNull('from_state_id');
        } else {
            $query->where('from_state_id', $fromStateId);
        }

        $rows = $query->orderBy('display_order')->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function findActiveFromState(int $blueprintId, ?int $fromStateId): array
    {
        $query = DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->where('is_active', true);

        if ($fromStateId === null) {
            $query->whereNull('from_state_id');
        } else {
            $query->where('from_state_id', $fromStateId);
        }

        $rows = $query->orderBy('display_order')->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function save(BlueprintTransition $transition): BlueprintTransition
    {
        $data = $this->toRowData($transition);

        if ($transition->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $transition->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $transition->getId();
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

    public function deleteByBlueprintId(int $blueprintId): int
    {
        return DB::table(self::TABLE)->where('blueprint_id', $blueprintId)->delete();
    }

    private function toDomainEntityWithRelations(stdClass $row): BlueprintTransition
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
        $conditions = DB::table(self::TABLE_CONDITIONS)
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
        $requirements = DB::table(self::TABLE_REQUIREMENTS)
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
        $actions = DB::table(self::TABLE_ACTIONS)
            ->where('transition_id', $row->id)
            ->get()
            ->map(fn($a) => [
                'type' => $a->action_type,
                'config' => $a->action_config ? (is_string($a->action_config) ? json_decode($a->action_config, true) : $a->action_config) : [],
            ])
            ->all();
        $transition->setActions($actions);

        // Load approval config
        $approval = DB::table(self::TABLE_APPROVALS)
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

    private function toRowData(BlueprintTransition $transition): array
    {
        return [
            'blueprint_id' => $transition->getBlueprintId(),
            'from_state_id' => $transition->getFromStateId(),
            'to_state_id' => $transition->getToStateId(),
            'name' => $transition->getName(),
            'description' => $transition->getDescription(),
            'button_label' => $transition->getButtonLabel(),
            'display_order' => $transition->getDisplayOrder(),
            'is_active' => $transition->isActive(),
        ];
    }
}
