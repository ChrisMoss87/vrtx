<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Forecasting;

use App\Domain\Forecasting\Entities\ForecastAdjustment;
use App\Domain\Forecasting\Repositories\ForecastAdjustmentRepositoryInterface;
use App\Domain\Forecasting\ValueObjects\AdjustmentType;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbForecastAdjustmentRepository implements ForecastAdjustmentRepositoryInterface
{
    private const TABLE = 'forecast_adjustments';

    public function findById(int $id): ?ForecastAdjustment
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findByRecord(int $moduleRecordId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('module_record_id', $moduleRecordId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function findByRecordAndType(
        int $moduleRecordId,
        AdjustmentType $type
    ): array {
        $rows = DB::table(self::TABLE)
            ->where('module_record_id', $moduleRecordId)
            ->where('adjustment_type', $type->value)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function findByUser(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function save(ForecastAdjustment $adjustment): ForecastAdjustment
    {
        $data = $this->toRowData($adjustment);

        if ($adjustment->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $adjustment->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $adjustment->getId();
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

    private function toDomainEntity(stdClass $row): ForecastAdjustment
    {
        return ForecastAdjustment::reconstitute(
            id: (int) $row->id,
            userId: UserId::fromInt((int) $row->user_id),
            moduleRecordId: (int) $row->module_record_id,
            adjustmentType: AdjustmentType::from($row->adjustment_type),
            oldValue: $row->old_value !== null ? (float) $row->old_value : null,
            newValue: $row->new_value !== null ? (float) $row->new_value : null,
            reason: $row->reason,
            createdAt: $row->created_at ? Timestamp::fromString($row->created_at) : null,
            updatedAt: $row->updated_at ? Timestamp::fromString($row->updated_at) : null,
        );
    }

    private function toRowData(ForecastAdjustment $adjustment): array
    {
        return [
            'user_id' => $adjustment->userId()->value(),
            'module_record_id' => $adjustment->moduleRecordId(),
            'adjustment_type' => $adjustment->adjustmentType()->value,
            'old_value' => $adjustment->oldValue(),
            'new_value' => $adjustment->newValue(),
            'reason' => $adjustment->reason(),
        ];
    }
}
