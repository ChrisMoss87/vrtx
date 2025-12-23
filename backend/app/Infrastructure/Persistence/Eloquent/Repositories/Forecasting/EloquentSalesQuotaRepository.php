<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Forecasting;

use App\Domain\Forecasting\Entities\SalesQuota;
use App\Domain\Forecasting\Repositories\SalesQuotaRepositoryInterface;
use App\Domain\Forecasting\ValueObjects\ForecastPeriod;
use App\Domain\Forecasting\ValueObjects\QuotaType;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class EloquentSalesQuotaRepository implements SalesQuotaRepositoryInterface
{
    private const TABLE = 'sales_quotas';

    public function findById(int $id): ?SalesQuota
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findByUser(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->orderByDesc('period_start')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function findByUserAndPeriod(
        int $userId,
        ForecastPeriod $period,
        ?int $pipelineId = null
    ): ?SalesQuota {
        $query = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('period_type', $period->type())
            ->where('period_start', '<=', $period->end()->format('Y-m-d'))
            ->where('period_end', '>=', $period->start()->format('Y-m-d'));

        if ($pipelineId !== null) {
            $query->where(function ($q) use ($pipelineId) {
                $q->whereNull('pipeline_id')
                    ->orWhere('pipeline_id', $pipelineId);
            });
        }

        $row = $query->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findByTeam(int $teamId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('team_id', $teamId)
            ->orderByDesc('period_start')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function findByTeamAndPeriod(
        int $teamId,
        ForecastPeriod $period,
        ?int $pipelineId = null
    ): ?SalesQuota {
        $query = DB::table(self::TABLE)
            ->where('team_id', $teamId)
            ->where('period_type', $period->type())
            ->where('period_start', '<=', $period->end()->format('Y-m-d'))
            ->where('period_end', '>=', $period->start()->format('Y-m-d'));

        if ($pipelineId !== null) {
            $query->where('pipeline_id', $pipelineId);
        }

        $row = $query->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findByPipeline(int $pipelineId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('pipeline_id', $pipelineId)
            ->orderByDesc('period_start')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function findCurrent(): array
    {
        $now = now()->format('Y-m-d');
        $rows = DB::table(self::TABLE)
            ->where('period_start', '<=', $now)
            ->where('period_end', '>=', $now)
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function save(SalesQuota $quota): SalesQuota
    {
        $data = $this->toRowData($quota);

        if ($quota->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $quota->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $quota->getId();
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

    private function toDomainEntity(stdClass $row): SalesQuota
    {
        return SalesQuota::reconstitute(
            id: (int) $row->id,
            userId: $row->user_id ? UserId::fromInt((int) $row->user_id) : null,
            pipelineId: $row->pipeline_id ? (int) $row->pipeline_id : null,
            teamId: $row->team_id ? (int) $row->team_id : null,
            period: ForecastPeriod::create(
                $row->period_type,
                new DateTimeImmutable($row->period_start),
                new DateTimeImmutable($row->period_end)
            ),
            quotaAmount: (float) $row->quota_amount,
            currency: $row->currency ?? 'USD',
            quotaType: QuotaType::tryFrom($row->quota_type ?? 'revenue') ?? QuotaType::REVENUE,
            notes: $row->notes,
            createdAt: $row->created_at ? Timestamp::fromString($row->created_at) : null,
            updatedAt: $row->updated_at ? Timestamp::fromString($row->updated_at) : null,
        );
    }

    private function toRowData(SalesQuota $quota): array
    {
        return [
            'user_id' => $quota->userId()?->value(),
            'pipeline_id' => $quota->pipelineId(),
            'team_id' => $quota->teamId(),
            'period_type' => $quota->period()->type(),
            'period_start' => $quota->period()->start()->format('Y-m-d'),
            'period_end' => $quota->period()->end()->format('Y-m-d'),
            'quota_amount' => $quota->quotaAmount(),
            'currency' => $quota->currency(),
            'quota_type' => $quota->quotaType()->value,
            'notes' => $quota->notes(),
        ];
    }
}
