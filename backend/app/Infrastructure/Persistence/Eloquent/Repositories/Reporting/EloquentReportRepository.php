<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Reporting;

use App\Domain\Reporting\Entities\Report;
use App\Domain\Reporting\Repositories\ReportRepositoryInterface;
use App\Domain\Reporting\ValueObjects\ChartType;
use App\Domain\Reporting\ValueObjects\DateRange;
use App\Domain\Reporting\ValueObjects\ReportType;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use App\Models\Report as ReportModel;

/**
 * Eloquent implementation of the ReportRepository.
 */
class EloquentReportRepository implements ReportRepositoryInterface
{
    public function findById(int $id): ?Report
    {
        $model = ReportModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findAll(): array
    {
        $models = ReportModel::orderBy('name')->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByModule(int $moduleId): array
    {
        $models = ReportModel::where('module_id', $moduleId)
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findAccessibleByUser(int $userId): array
    {
        $models = ReportModel::where(function ($query) use ($userId) {
            $query->where('user_id', $userId)
                  ->orWhere('is_public', true);
        })
        ->orderBy('name')
        ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findPublic(): array
    {
        $models = ReportModel::where('is_public', true)
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findFavoritesByUser(int $userId): array
    {
        $models = ReportModel::where('user_id', $userId)
            ->where('is_favorite', true)
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByType(ReportType $type): array
    {
        $models = ReportModel::where('type', $type->value)
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findScheduledForExecution(): array
    {
        $models = ReportModel::whereNotNull('schedule')
            ->where(function ($query) {
                $query->whereNull('last_run_at')
                      ->orWhereRaw('DATE(last_run_at) < CURRENT_DATE');
            })
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(Report $report): Report
    {
        $data = $this->toModelData($report);

        if ($report->getId() !== null) {
            $model = ReportModel::findOrFail($report->getId());
            $model->update($data);
        } else {
            $model = ReportModel::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    public function delete(int $id): bool
    {
        $model = ReportModel::find($id);

        if (!$model) {
            return false;
        }

        return $model->delete() ?? false;
    }

    public function forceDelete(int $id): bool
    {
        $model = ReportModel::withTrashed()->find($id);

        if (!$model) {
            return false;
        }

        return $model->forceDelete() ?? false;
    }

    public function restore(int $id): bool
    {
        $model = ReportModel::withTrashed()->find($id);

        if (!$model || !$model->trashed()) {
            return false;
        }

        return $model->restore() ?? false;
    }

    /**
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(ReportModel $model): Report
    {
        return Report::reconstitute(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            moduleId: $model->module_id,
            userId: $model->user_id ? UserId::fromInt($model->user_id) : null,
            type: ReportType::from($model->type),
            chartType: $model->chart_type ? ChartType::from($model->chart_type) : null,
            isPublic: $model->is_public,
            isFavorite: $model->is_favorite,
            config: $model->config ?? [],
            filters: $model->filters ?? [],
            grouping: $model->grouping ?? [],
            aggregations: $model->aggregations ?? [],
            sorting: $model->sorting ?? [],
            dateRange: $model->date_range ? DateRange::fromArray($model->date_range) : null,
            schedule: $model->schedule,
            lastRunAt: $model->last_run_at
                ? Timestamp::fromDateTime($model->last_run_at)
                : null,
            cachedResult: $model->cached_result,
            cacheExpiresAt: $model->cache_expires_at
                ? Timestamp::fromDateTime($model->cache_expires_at)
                : null,
            createdAt: $model->created_at
                ? Timestamp::fromDateTime($model->created_at)
                : null,
            updatedAt: $model->updated_at
                ? Timestamp::fromDateTime($model->updated_at)
                : null,
            deletedAt: $model->deleted_at
                ? Timestamp::fromDateTime($model->deleted_at)
                : null,
        );
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(Report $report): array
    {
        return [
            'name' => $report->name(),
            'description' => $report->description(),
            'module_id' => $report->moduleId(),
            'user_id' => $report->userId()?->value(),
            'type' => $report->type()->value,
            'chart_type' => $report->chartType()?->value,
            'is_public' => $report->isPublic(),
            'is_favorite' => $report->isFavorite(),
            'config' => $report->config(),
            'filters' => $report->filters(),
            'grouping' => $report->grouping(),
            'aggregations' => $report->aggregations(),
            'sorting' => $report->sorting(),
            'date_range' => $report->dateRange()?->toArray() ?? [],
            'schedule' => $report->schedule(),
            'last_run_at' => $report->lastRunAt()?->toDateTimeString(),
            'cached_result' => $report->cachedResult(),
            'cache_expires_at' => $report->cacheExpiresAt()?->toDateTimeString(),
        ];
    }
}
