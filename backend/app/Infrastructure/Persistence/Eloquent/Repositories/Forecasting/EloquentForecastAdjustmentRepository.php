<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Forecasting;

use App\Domain\Forecasting\Entities\ForecastAdjustment;
use App\Domain\Forecasting\Repositories\ForecastAdjustmentRepositoryInterface;
use App\Domain\Forecasting\ValueObjects\AdjustmentType;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use App\Models\ForecastAdjustment as ForecastAdjustmentModel;

class EloquentForecastAdjustmentRepository implements ForecastAdjustmentRepositoryInterface
{
    public function findById(int $id): ?ForecastAdjustment
    {
        $model = ForecastAdjustmentModel::find($id);
        return $model ? $this->toDomainEntity($model) : null;
    }

    public function findByRecord(int $moduleRecordId): array
    {
        $models = ForecastAdjustmentModel::where('module_record_id', $moduleRecordId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByRecordAndType(
        int $moduleRecordId,
        AdjustmentType $type
    ): array {
        $models = ForecastAdjustmentModel::where('module_record_id', $moduleRecordId)
            ->where('adjustment_type', $type->value)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByUser(int $userId): array
    {
        $models = ForecastAdjustmentModel::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(ForecastAdjustment $adjustment): ForecastAdjustment
    {
        $data = $this->toModelData($adjustment);

        if ($adjustment->getId() !== null) {
            $model = ForecastAdjustmentModel::findOrFail($adjustment->getId());
            $model->update($data);
        } else {
            $model = ForecastAdjustmentModel::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    public function delete(int $id): bool
    {
        $model = ForecastAdjustmentModel::find($id);
        return $model ? ($model->delete() ?? false) : false;
    }

    private function toDomainEntity(ForecastAdjustmentModel $model): ForecastAdjustment
    {
        return ForecastAdjustment::reconstitute(
            id: $model->id,
            userId: UserId::fromInt($model->user_id),
            moduleRecordId: $model->module_record_id,
            adjustmentType: AdjustmentType::from($model->adjustment_type),
            oldValue: $model->old_value,
            newValue: $model->new_value,
            reason: $model->reason,
            createdAt: $model->created_at ? Timestamp::fromDateTime($model->created_at) : null,
            updatedAt: $model->updated_at ? Timestamp::fromDateTime($model->updated_at) : null,
        );
    }

    private function toModelData(ForecastAdjustment $adjustment): array
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
