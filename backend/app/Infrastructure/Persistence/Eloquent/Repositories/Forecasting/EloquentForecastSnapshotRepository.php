<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Forecasting;

use App\Domain\Forecasting\Entities\ForecastSnapshot;
use App\Domain\Forecasting\Repositories\ForecastSnapshotRepositoryInterface;
use App\Domain\Forecasting\ValueObjects\ForecastPeriod;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use App\Models\ForecastSnapshot as ForecastSnapshotModel;
use DateTimeImmutable;

class EloquentForecastSnapshotRepository implements ForecastSnapshotRepositoryInterface
{
    public function findById(int $id): ?ForecastSnapshot
    {
        $model = ForecastSnapshotModel::find($id);
        return $model ? $this->toDomainEntity($model) : null;
    }

    public function findByPipelineAndDate(
        int $pipelineId,
        ForecastPeriod $period,
        DateTimeImmutable $snapshotDate,
        ?int $userId = null
    ): ?ForecastSnapshot {
        $query = ForecastSnapshotModel::where('pipeline_id', $pipelineId)
            ->where('period_type', $period->type())
            ->where('period_start', $period->start()->format('Y-m-d'))
            ->where('snapshot_date', $snapshotDate->format('Y-m-d'));

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $model = $query->first();
        return $model ? $this->toDomainEntity($model) : null;
    }

    public function findLatestByPipeline(
        int $pipelineId,
        ForecastPeriod $period,
        ?int $userId = null
    ): ?ForecastSnapshot {
        $query = ForecastSnapshotModel::where('pipeline_id', $pipelineId)
            ->where('period_type', $period->type())
            ->where('period_start', $period->start()->format('Y-m-d'))
            ->orderBy('snapshot_date', 'desc');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $model = $query->first();
        return $model ? $this->toDomainEntity($model) : null;
    }

    public function findHistoryByPipeline(
        int $pipelineId,
        string $periodType,
        ?int $userId = null,
        int $limit = 12
    ): array {
        $query = ForecastSnapshotModel::where('pipeline_id', $pipelineId)
            ->where('period_type', $periodType)
            ->orderBy('snapshot_date', 'desc')
            ->limit($limit);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->get()->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByPeriod(
        int $pipelineId,
        ForecastPeriod $period,
        ?int $userId = null
    ): array {
        $query = ForecastSnapshotModel::where('pipeline_id', $pipelineId)
            ->where('period_type', $period->type())
            ->where('period_start', $period->start()->format('Y-m-d'))
            ->orderBy('snapshot_date', 'desc');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->get()->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(ForecastSnapshot $snapshot): ForecastSnapshot
    {
        $data = $this->toModelData($snapshot);

        if ($snapshot->getId() !== null) {
            $model = ForecastSnapshotModel::findOrFail($snapshot->getId());
            $model->update($data);
        } else {
            $model = ForecastSnapshotModel::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    public function deleteOlderThan(DateTimeImmutable $date): int
    {
        return ForecastSnapshotModel::where('snapshot_date', '<', $date->format('Y-m-d'))
            ->delete();
    }

    public function delete(int $id): bool
    {
        $model = ForecastSnapshotModel::find($id);
        return $model ? ($model->delete() ?? false) : false;
    }

    private function toDomainEntity(ForecastSnapshotModel $model): ForecastSnapshot
    {
        return ForecastSnapshot::reconstitute(
            id: $model->id,
            userId: $model->user_id ? UserId::fromInt($model->user_id) : null,
            pipelineId: $model->pipeline_id,
            period: ForecastPeriod::create(
                $model->period_type,
                new DateTimeImmutable($model->period_start->toDateString()),
                new DateTimeImmutable($model->period_end->toDateString())
            ),
            snapshotDate: new DateTimeImmutable($model->snapshot_date->toDateString()),
            commitAmount: (float) $model->commit_amount,
            bestCaseAmount: (float) $model->best_case_amount,
            pipelineAmount: (float) $model->pipeline_amount,
            weightedAmount: (float) $model->weighted_amount,
            closedWonAmount: (float) $model->closed_won_amount,
            dealCount: $model->deal_count ?? 0,
            metadata: $model->metadata ?? [],
            createdAt: $model->created_at ? Timestamp::fromDateTime($model->created_at) : null,
            updatedAt: $model->updated_at ? Timestamp::fromDateTime($model->updated_at) : null,
        );
    }

    private function toModelData(ForecastSnapshot $snapshot): array
    {
        return [
            'user_id' => $snapshot->userId()?->value(),
            'pipeline_id' => $snapshot->pipelineId(),
            'period_type' => $snapshot->period()->type(),
            'period_start' => $snapshot->period()->start()->format('Y-m-d'),
            'period_end' => $snapshot->period()->end()->format('Y-m-d'),
            'snapshot_date' => $snapshot->snapshotDate()->format('Y-m-d'),
            'commit_amount' => $snapshot->commitAmount(),
            'best_case_amount' => $snapshot->bestCaseAmount(),
            'pipeline_amount' => $snapshot->pipelineAmount(),
            'weighted_amount' => $snapshot->weightedAmount(),
            'closed_won_amount' => $snapshot->closedWonAmount(),
            'deal_count' => $snapshot->dealCount(),
            'metadata' => $snapshot->metadata(),
        ];
    }
}
