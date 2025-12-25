<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Blueprint;

use App\Domain\Blueprint\Entities\BlueprintState;
use App\Domain\Blueprint\Repositories\BlueprintStateRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbBlueprintStateRepository implements BlueprintStateRepositoryInterface
{
    private const TABLE = 'blueprint_states';

    public function findById(int $id): ?BlueprintState
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByBlueprintId(int $blueprintId): array
    {
        $rows = DB::table(self::TABLE)->where('blueprint_id', $blueprintId)->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findInitialState(int $blueprintId): ?BlueprintState
    {
        $row = DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->where('is_initial', true)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByFieldOptionValue(int $blueprintId, string $value): ?BlueprintState
    {
        $row = DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->where('field_option_value', $value)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(BlueprintState $state): BlueprintState
    {
        $data = $this->toRowData($state);

        if ($state->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $state->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $state->getId();
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

    private function toDomainEntity(stdClass $row): BlueprintState
    {
        return BlueprintState::reconstitute(
            id: (int) $row->id,
            blueprintId: (int) $row->blueprint_id,
            name: $row->name,
            fieldOptionValue: $row->field_option_value,
            color: $row->color,
            isInitial: (bool) $row->is_initial,
            isTerminal: (bool) $row->is_terminal,
            positionX: (int) ($row->position_x ?? 0),
            positionY: (int) ($row->position_y ?? 0),
            metadata: $row->metadata ? (is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata) : [],
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    private function toRowData(BlueprintState $state): array
    {
        return [
            'blueprint_id' => $state->getBlueprintId(),
            'name' => $state->getName(),
            'field_option_value' => $state->getFieldOptionValue(),
            'color' => $state->getColor(),
            'is_initial' => $state->isInitial(),
            'is_terminal' => $state->isTerminal(),
            'position_x' => $state->getPositionX(),
            'position_y' => $state->getPositionY(),
            'metadata' => json_encode($state->getMetadata()),
        ];
    }
}
