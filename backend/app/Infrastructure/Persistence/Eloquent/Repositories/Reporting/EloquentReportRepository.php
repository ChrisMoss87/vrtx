<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Reporting;

use App\Domain\Reporting\Entities\Report;
use App\Domain\Reporting\Repositories\ReportRepositoryInterface;
use App\Domain\Reporting\ValueObjects\ChartType;
use App\Domain\Reporting\ValueObjects\DateRange;
use App\Domain\Reporting\ValueObjects\ReportType;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Query Builder implementation of the ReportRepository.
 */
class EloquentReportRepository implements ReportRepositoryInterface
{
    private const TABLE = 'reports';

    // Available types
    private const TYPES = ['tabular', 'summary', 'matrix', 'chart'];
    private const CHART_TYPES = ['bar', 'line', 'pie', 'donut', 'area', 'funnel'];
    private const AGGREGATIONS = ['count', 'sum', 'avg', 'min', 'max'];

    public function findById(int $id): ?Report
    {
        $row = DB::table(self::TABLE)->where('id', $id)->whereNull('deleted_at')->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findAll(): array
    {
        $rows = DB::table(self::TABLE)->whereNull('deleted_at')->orderBy('name')->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findByModule(int $moduleId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findAccessibleByUser(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhere('is_public', true);
            })
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findPublic(): array
    {
        $rows = DB::table(self::TABLE)
            ->where('is_public', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findFavoritesByUser(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('is_favorite', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findByType(ReportType $type): array
    {
        $rows = DB::table(self::TABLE)
            ->where('type', $type->value)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findScheduledForExecution(): array
    {
        $rows = DB::table(self::TABLE)
            ->whereNotNull('schedule')
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNull('last_run_at')
                    ->orWhereRaw('DATE(last_run_at) < CURRENT_DATE');
            })
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function save(Report $report): Report
    {
        $data = $this->toRowData($report);

        if ($report->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $report->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $report->getId();
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
        // Soft delete
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->update(['deleted_at' => now()]) > 0;
    }

    public function forceDelete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function restore(int $id): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->whereNotNull('deleted_at')
            ->update(['deleted_at' => null]) > 0;
    }

    public function findByIdAsArray(int $id, array $relations = []): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->whereNull('deleted_at')->first();

        if (!$row) {
            return null;
        }

        return $this->rowToArrayWithRelations($row);
    }

    public function findAllAsArrays(array $relations = []): array
    {
        $rows = DB::table(self::TABLE)->whereNull('deleted_at')->orderBy('name')->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();
    }

    public function findByModuleAsArrays(int $moduleId, array $relations = []): array
    {
        $rows = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();
    }

    public function findAccessibleByUserAsArrays(int $userId, array $relations = []): array
    {
        $rows = DB::table(self::TABLE)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere('is_public', true);
            })
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();
    }

    public function findAccessibleByUserPaginated(
        int $userId,
        int $page = 1,
        int $perPage = 20,
        array $filters = [],
        array $relations = []
    ): PaginatedResult {
        $query = DB::table(self::TABLE)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere('is_public', true);
            })
            ->whereNull('deleted_at');

        // Apply filters
        if (isset($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['favorites']) && $filters['favorites']) {
            $query->where('is_favorite', true);
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $query->where('name', 'ilike', '%' . $filters['search'] . '%');
        }

        // Apply sorting
        $query->orderByDesc('is_favorite')
            ->orderByDesc('updated_at');

        $total = $query->count();

        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return PaginatedResult::create(
            items: $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all(),
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function findPublicAsArrays(array $relations = []): array
    {
        $rows = DB::table(self::TABLE)
            ->where('is_public', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();
    }

    public function findFavoritesByUserAsArrays(int $userId, array $relations = []): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('is_favorite', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();
    }

    public function findByTypeAsArrays(ReportType $type, array $relations = []): array
    {
        $rows = DB::table(self::TABLE)
            ->where('type', $type->value)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();
    }

    public function findScheduledForExecutionAsArrays(array $relations = []): array
    {
        $rows = DB::table(self::TABLE)
            ->whereNotNull('schedule')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('last_run_at')
                    ->orWhereRaw('DATE(last_run_at) < CURRENT_DATE');
            })
            ->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();
    }

    public function updateById(int $id, array $data): bool
    {
        if (isset($data['config']) && is_array($data['config'])) {
            $data['config'] = json_encode($data['config']);
        }
        if (isset($data['filters']) && is_array($data['filters'])) {
            $data['filters'] = json_encode($data['filters']);
        }
        if (isset($data['grouping']) && is_array($data['grouping'])) {
            $data['grouping'] = json_encode($data['grouping']);
        }
        if (isset($data['aggregations']) && is_array($data['aggregations'])) {
            $data['aggregations'] = json_encode($data['aggregations']);
        }
        if (isset($data['sorting']) && is_array($data['sorting'])) {
            $data['sorting'] = json_encode($data['sorting']);
        }
        if (isset($data['date_range']) && is_array($data['date_range'])) {
            $data['date_range'] = json_encode($data['date_range']);
        }
        if (isset($data['cached_result']) && is_array($data['cached_result'])) {
            $data['cached_result'] = json_encode($data['cached_result']);
        }

        return DB::table(self::TABLE)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()])) > 0;
    }

    public function getAvailableTypes(): array
    {
        return self::TYPES;
    }

    public function getAvailableChartTypes(): array
    {
        return self::CHART_TYPES;
    }

    public function getAvailableAggregations(): array
    {
        return self::AGGREGATIONS;
    }

    /**
     * Convert a database row to a domain entity.
     */
    private function toDomainEntity(stdClass $row): Report
    {
        return Report::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            description: $row->description,
            moduleId: $row->module_id ? (int) $row->module_id : null,
            userId: $row->user_id ? UserId::fromInt((int) $row->user_id) : null,
            type: ReportType::from($row->type),
            chartType: $row->chart_type ? ChartType::from($row->chart_type) : null,
            isPublic: (bool) $row->is_public,
            isFavorite: (bool) $row->is_favorite,
            config: $row->config ? (is_string($row->config) ? json_decode($row->config, true) : $row->config) : [],
            filters: $row->filters ? (is_string($row->filters) ? json_decode($row->filters, true) : $row->filters) : [],
            grouping: $row->grouping ? (is_string($row->grouping) ? json_decode($row->grouping, true) : $row->grouping) : [],
            aggregations: $row->aggregations ? (is_string($row->aggregations) ? json_decode($row->aggregations, true) : $row->aggregations) : [],
            sorting: $row->sorting ? (is_string($row->sorting) ? json_decode($row->sorting, true) : $row->sorting) : [],
            dateRange: $row->date_range ? DateRange::fromArray(is_string($row->date_range) ? json_decode($row->date_range, true) : $row->date_range) : null,
            schedule: $row->schedule ? (is_string($row->schedule) ? json_decode($row->schedule, true) : $row->schedule) : null,
            lastRunAt: $row->last_run_at ? Timestamp::fromString($row->last_run_at) : null,
            cachedResult: $row->cached_result ? (is_string($row->cached_result) ? json_decode($row->cached_result, true) : $row->cached_result) : null,
            cacheExpiresAt: $row->cache_expires_at ? Timestamp::fromString($row->cache_expires_at) : null,
            createdAt: $row->created_at ? Timestamp::fromString($row->created_at) : null,
            updatedAt: $row->updated_at ? Timestamp::fromString($row->updated_at) : null,
            deletedAt: $row->deleted_at ? Timestamp::fromString($row->deleted_at) : null,
        );
    }

    /**
     * Convert a domain entity to row data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(Report $report): array
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
            'config' => json_encode($report->config()),
            'filters' => json_encode($report->filters()),
            'grouping' => json_encode($report->grouping()),
            'aggregations' => json_encode($report->aggregations()),
            'sorting' => json_encode($report->sorting()),
            'date_range' => $report->dateRange() ? json_encode($report->dateRange()->toArray()) : null,
            'schedule' => $report->schedule() ? json_encode($report->schedule()) : null,
            'last_run_at' => $report->lastRunAt()?->toDateTimeString(),
            'cached_result' => $report->cachedResult() ? json_encode($report->cachedResult()) : null,
            'cache_expires_at' => $report->cacheExpiresAt()?->toDateTimeString(),
        ];
    }

    /**
     * Convert a database row to array with relations.
     */
    private function rowToArrayWithRelations(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'module_id' => $row->module_id,
            'user_id' => $row->user_id,
            'type' => $row->type,
            'chart_type' => $row->chart_type,
            'is_public' => (bool) $row->is_public,
            'is_favorite' => (bool) $row->is_favorite,
            'config' => $row->config ? (is_string($row->config) ? json_decode($row->config, true) : $row->config) : [],
            'filters' => $row->filters ? (is_string($row->filters) ? json_decode($row->filters, true) : $row->filters) : [],
            'grouping' => $row->grouping ? (is_string($row->grouping) ? json_decode($row->grouping, true) : $row->grouping) : [],
            'aggregations' => $row->aggregations ? (is_string($row->aggregations) ? json_decode($row->aggregations, true) : $row->aggregations) : [],
            'sorting' => $row->sorting ? (is_string($row->sorting) ? json_decode($row->sorting, true) : $row->sorting) : [],
            'date_range' => $row->date_range ? (is_string($row->date_range) ? json_decode($row->date_range, true) : $row->date_range) : null,
            'schedule' => $row->schedule ? (is_string($row->schedule) ? json_decode($row->schedule, true) : $row->schedule) : null,
            'last_run_at' => $row->last_run_at,
            'cached_result' => $row->cached_result ? (is_string($row->cached_result) ? json_decode($row->cached_result, true) : $row->cached_result) : null,
            'cache_expires_at' => $row->cache_expires_at,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'deleted_at' => $row->deleted_at,
        ];
    }
}
