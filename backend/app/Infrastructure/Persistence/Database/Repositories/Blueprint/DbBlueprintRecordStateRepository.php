<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Blueprint;

use App\Domain\Blueprint\Entities\BlueprintRecordState;
use App\Domain\Blueprint\Repositories\BlueprintRecordStateRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbBlueprintRecordStateRepository implements BlueprintRecordStateRepositoryInterface
{
    private const TABLE = 'blueprint_record_states';

    public function findById(int $id): ?BlueprintRecordState
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByRecordId(int $blueprintId, int $recordId): ?BlueprintRecordState
    {
        $row = DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->where('record_id', $recordId)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByStateId(int $stateId): array
    {
        $rows = DB::table(self::TABLE)->where('current_state_id', $stateId)->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findByBlueprintId(int $blueprintId): array
    {
        $rows = DB::table(self::TABLE)->where('blueprint_id', $blueprintId)->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function save(BlueprintRecordState $recordState): BlueprintRecordState
    {
        $data = $this->toRowData($recordState);

        if ($recordState->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $recordState->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $recordState->getId();
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

    public function deleteByRecordId(int $blueprintId, int $recordId): bool
    {
        return DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->where('record_id', $recordId)
            ->delete() > 0;
    }

    private function toDomainEntity(stdClass $row): BlueprintRecordState
    {
        return BlueprintRecordState::reconstitute(
            id: (int) $row->id,
            blueprintId: (int) $row->blueprint_id,
            recordId: (int) $row->record_id,
            currentStateId: (int) $row->current_state_id,
            enteredStateAt: $row->state_entered_at ? new DateTimeImmutable($row->state_entered_at) : new DateTimeImmutable(),
            slaInstanceId: $row->sla_instance_id ? (int) $row->sla_instance_id : null,
            metadata: $row->metadata ? (is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata) : [],
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    private function toRowData(BlueprintRecordState $recordState): array
    {
        return [
            'blueprint_id' => $recordState->getBlueprintId(),
            'record_id' => $recordState->getRecordId(),
            'current_state_id' => $recordState->getCurrentStateId(),
            'state_entered_at' => $recordState->getEnteredStateAt()?->format('Y-m-d H:i:s'),
            'sla_instance_id' => $recordState->getSlaInstanceId(),
            'metadata' => json_encode($recordState->getMetadata()),
        ];
    }
}
