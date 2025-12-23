<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Forecasting;

use App\Domain\Forecasting\Entities\ForecastSnapshot;
use App\Domain\Forecasting\Repositories\ForecastSnapshotRepositoryInterface;
use App\Domain\Forecasting\ValueObjects\ForecastPeriod;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class EloquentForecastSnapshotRepository implements ForecastSnapshotRepositoryInterface
{
    private const TABLE = 'forecast_snapshots';

    public function findById(int $id): ?ForecastSnapshot
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findByPipelineAndDate(
        int $pipelineId,
        ForecastPeriod $period,
        DateTimeImmutable $snapshotDate,
        ?int $userId = null
    ): ?ForecastSnapshot {
        $query = DB::table(self::TABLE)
            ->where('pipeline_id', $pipelineId)
            ->where('period_type', $period->type())
            ->where('period_start', $period->start()->format('Y-m-d'))
            ->where('snapshot_date', $snapshotDate->format('Y-m-d'));

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $row = $query->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findLatestByPipeline(
        int $pipelineId,
        ForecastPeriod $period,
        ?int $userId = null
    ): ?ForecastSnapshot {
        $query = DB::table(self::TABLE)
            ->where('pipeline_id', $pipelineId)
            ->where('period_type', $period->type())
            ->where('period_start', $period->start()->format('Y-m-d'))
            ->orderByDesc('snapshot_date');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $row = $query->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findHistoryByPipeline(
        int $pipelineId,
        string $periodType,
        ?int $userId = null,
        int $limit = 12
    ): array {
        $query = DB::table(self::TABLE)
            ->where('pipeline_id', $pipelineId)
            ->where('period_type', $periodType)
            ->orderByDesc('snapshot_date')
            ->limit($limit);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $rows = $query->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function findByPeriod(
        int $pipelineId,
        ForecastPeriod $period,
        ?int $userId = null
    ): array {
        $query = DB::table(self::TABLE)
            ->where('pipeline_id', $pipelineId)
            ->where('period_type', $period->type())
            ->where('period_start', $period->start()->format('Y-m-d'))
            ->orderByDesc('snapshot_date');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $rows = $query->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function save(ForecastSnapshot $snapshot): ForecastSnapshot
    {
        $data = $this->toRowData($snapshot);

        if ($snapshot->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $snapshot->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $snapshot->getId();
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

    public function deleteOlderThan(DateTimeImmutable $date): int
    {
        return DB::table(self::TABLE)
            ->where('snapshot_date', '<', $date->format('Y-m-d'))
            ->delete();
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    private function toDomainEntity(stdClass $row): ForecastSnapshot
    {
        return ForecastSnapshot::reconstitute(
            id: (int) $row->id,
            userId: $row->user_id ? UserId::fromInt((int) $row->user_id) : null,
            pipelineId: (int) $row->pipeline_id,
            period: ForecastPeriod::create(
                $row->period_type,
                new DateTimeImmutable($row->period_start),
                new DateTimeImmutable($row->period_end)
            ),
            snapshotDate: new DateTimeImmutable($row->snapshot_date),
            commitAmount: (float) $row->commit_amount,
            bestCaseAmount: (float) $row->best_case_amount,
            pipelineAmount: (float) $row->pipeline_amount,
            weightedAmount: (float) $row->weighted_amount,
            closedWonAmount: (float) $row->closed_won_amount,
            dealCount: (int) ($row->deal_count ?? 0),
            metadata: $row->metadata ? (is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata) : [],
            createdAt: $row->created_at ? Timestamp::fromString($row->created_at) : null,
            updatedAt: $row->updated_at ? Timestamp::fromString($row->updated_at) : null,
        );
    }

    private function toRowData(ForecastSnapshot $snapshot): array
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
            'metadata' => json_encode($snapshot->metadata()),
        ];
    }
}
