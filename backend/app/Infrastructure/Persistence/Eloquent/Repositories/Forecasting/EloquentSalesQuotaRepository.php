<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Forecasting;

use App\Domain\Forecasting\Entities\SalesQuota;
use App\Domain\Forecasting\Repositories\SalesQuotaRepositoryInterface;
use App\Domain\Forecasting\ValueObjects\ForecastPeriod;
use App\Domain\Forecasting\ValueObjects\QuotaType;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use App\Models\SalesQuota as SalesQuotaModel;
use DateTimeImmutable;

class EloquentSalesQuotaRepository implements SalesQuotaRepositoryInterface
{
    public function findById(int $id): ?SalesQuota
    {
        $model = SalesQuotaModel::find($id);
        return $model ? $this->toDomainEntity($model) : null;
    }

    public function findByUser(int $userId): array
    {
        $models = SalesQuotaModel::where('user_id', $userId)
            ->orderBy('period_start', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByUserAndPeriod(
        int $userId,
        ForecastPeriod $period,
        ?int $pipelineId = null
    ): ?SalesQuota {
        $query = SalesQuotaModel::where('user_id', $userId)
            ->where('period_type', $period->type())
            ->where('period_start', '<=', $period->end()->format('Y-m-d'))
            ->where('period_end', '>=', $period->start()->format('Y-m-d'));

        if ($pipelineId !== null) {
            $query->where(function ($q) use ($pipelineId) {
                $q->whereNull('pipeline_id')
                    ->orWhere('pipeline_id', $pipelineId);
            });
        }

        $model = $query->first();
        return $model ? $this->toDomainEntity($model) : null;
    }

    public function findByTeam(int $teamId): array
    {
        $models = SalesQuotaModel::where('team_id', $teamId)
            ->orderBy('period_start', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByTeamAndPeriod(
        int $teamId,
        ForecastPeriod $period,
        ?int $pipelineId = null
    ): ?SalesQuota {
        $query = SalesQuotaModel::where('team_id', $teamId)
            ->where('period_type', $period->type())
            ->where('period_start', '<=', $period->end()->format('Y-m-d'))
            ->where('period_end', '>=', $period->start()->format('Y-m-d'));

        if ($pipelineId !== null) {
            $query->where('pipeline_id', $pipelineId);
        }

        $model = $query->first();
        return $model ? $this->toDomainEntity($model) : null;
    }

    public function findByPipeline(int $pipelineId): array
    {
        $models = SalesQuotaModel::where('pipeline_id', $pipelineId)
            ->orderBy('period_start', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findCurrent(): array
    {
        $now = now();
        $models = SalesQuotaModel::where('period_start', '<=', $now)
            ->where('period_end', '>=', $now)
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(SalesQuota $quota): SalesQuota
    {
        $data = $this->toModelData($quota);

        if ($quota->getId() !== null) {
            $model = SalesQuotaModel::findOrFail($quota->getId());
            $model->update($data);
        } else {
            $model = SalesQuotaModel::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    public function delete(int $id): bool
    {
        $model = SalesQuotaModel::find($id);
        return $model ? ($model->delete() ?? false) : false;
    }

    private function toDomainEntity(SalesQuotaModel $model): SalesQuota
    {
        return SalesQuota::reconstitute(
            id: $model->id,
            userId: $model->user_id ? UserId::fromInt($model->user_id) : null,
            pipelineId: $model->pipeline_id,
            teamId: $model->team_id,
            period: ForecastPeriod::create(
                $model->period_type,
                new DateTimeImmutable($model->period_start->toDateString()),
                new DateTimeImmutable($model->period_end->toDateString())
            ),
            quotaAmount: (float) $model->quota_amount,
            currency: $model->currency ?? 'USD',
            quotaType: QuotaType::tryFrom($model->quota_type ?? 'revenue') ?? QuotaType::REVENUE,
            notes: $model->notes,
            createdAt: $model->created_at ? Timestamp::fromDateTime($model->created_at) : null,
            updatedAt: $model->updated_at ? Timestamp::fromDateTime($model->updated_at) : null,
        );
    }

    private function toModelData(SalesQuota $quota): array
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
