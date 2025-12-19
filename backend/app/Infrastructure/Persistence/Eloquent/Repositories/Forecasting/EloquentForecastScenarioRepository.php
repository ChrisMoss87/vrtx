<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Forecasting;

use App\Domain\Forecasting\Entities\ForecastScenario;
use App\Domain\Forecasting\Repositories\ForecastScenarioRepositoryInterface;
use App\Domain\Forecasting\ValueObjects\ScenarioType;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use App\Models\ForecastScenario as ForecastScenarioModel;
use DateTimeImmutable;

/**
 * Eloquent implementation of the ForecastScenarioRepository.
 */
class EloquentForecastScenarioRepository implements ForecastScenarioRepositoryInterface
{
    public function findById(int $id): ?ForecastScenario
    {
        $model = ForecastScenarioModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByUser(int $userId): array
    {
        $models = ForecastScenarioModel::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByUserAndModule(int $userId, int $moduleId): array
    {
        $models = ForecastScenarioModel::where('user_id', $userId)
            ->where('module_id', $moduleId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByPeriod(
        int $moduleId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        ?int $userId = null
    ): array {
        $query = ForecastScenarioModel::where('module_id', $moduleId)
            ->where('period_start', '<=', $periodEnd->format('Y-m-d'))
            ->where('period_end', '>=', $periodStart->format('Y-m-d'));

        if ($userId !== null) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere('is_shared', true);
            });
        }

        $models = $query->orderBy('created_at', 'desc')->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findBaseline(
        int $moduleId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        ?int $userId = null
    ): ?ForecastScenario {
        $query = ForecastScenarioModel::where('module_id', $moduleId)
            ->where('is_baseline', true)
            ->where('period_start', '<=', $periodEnd->format('Y-m-d'))
            ->where('period_end', '>=', $periodStart->format('Y-m-d'));

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $model = $query->first();

        return $model ? $this->toDomainEntity($model) : null;
    }

    public function findShared(int $moduleId): array
    {
        $models = ForecastScenarioModel::where('module_id', $moduleId)
            ->where('is_shared', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(ForecastScenario $scenario): ForecastScenario
    {
        $data = $this->toModelData($scenario);

        if ($scenario->getId() !== null) {
            $model = ForecastScenarioModel::findOrFail($scenario->getId());
            $model->update($data);
        } else {
            $model = ForecastScenarioModel::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    public function delete(int $id): bool
    {
        $model = ForecastScenarioModel::find($id);

        if (!$model) {
            return false;
        }

        return $model->delete() ?? false;
    }

    /**
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(ForecastScenarioModel $model): ForecastScenario
    {
        return ForecastScenario::reconstitute(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            userId: UserId::fromInt($model->user_id),
            moduleId: $model->module_id,
            periodStart: new DateTimeImmutable($model->period_start->toDateString()),
            periodEnd: new DateTimeImmutable($model->period_end->toDateString()),
            scenarioType: ScenarioType::from($model->scenario_type),
            isBaseline: $model->is_baseline,
            isShared: $model->is_shared,
            totalWeighted: (float) $model->total_weighted,
            totalUnweighted: (float) $model->total_unweighted,
            targetAmount: $model->target_amount ? (float) $model->target_amount : null,
            dealCount: $model->deal_count ?? 0,
            settings: $model->settings ?? [],
            createdAt: $model->created_at
                ? Timestamp::fromDateTime($model->created_at)
                : null,
            updatedAt: $model->updated_at
                ? Timestamp::fromDateTime($model->updated_at)
                : null,
        );
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(ForecastScenario $scenario): array
    {
        return [
            'name' => $scenario->name(),
            'description' => $scenario->description(),
            'user_id' => $scenario->userId()->value(),
            'module_id' => $scenario->moduleId(),
            'period_start' => $scenario->periodStart()->format('Y-m-d'),
            'period_end' => $scenario->periodEnd()->format('Y-m-d'),
            'scenario_type' => $scenario->scenarioType()->value,
            'is_baseline' => $scenario->isBaseline(),
            'is_shared' => $scenario->isShared(),
            'total_weighted' => $scenario->totalWeighted(),
            'total_unweighted' => $scenario->totalUnweighted(),
            'target_amount' => $scenario->targetAmount(),
            'deal_count' => $scenario->dealCount(),
            'settings' => $scenario->settings(),
        ];
    }
}
