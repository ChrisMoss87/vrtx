<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Modules\Entities\ModuleRecord as ModuleRecordEntity;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Models\ModuleRecord;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final class EloquentModuleRecordRepository implements ModuleRecordRepositoryInterface
{
    public function findById(int $moduleId, int $recordId): ?ModuleRecordEntity
    {
        $model = ModuleRecord::where('module_id', $moduleId)
            ->find($recordId);

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(
        int $moduleId,
        array $filters = [],
        array $sort = [],
        int $page = 1,
        int $perPage = 15
    ): array {
        $query = ModuleRecord::where('module_id', $moduleId);

        // Apply filters
        $query = $this->applyFilters($query, $filters);

        // Apply sorting
        $query = $this->applySorting($query, $sort);

        // Get paginated results
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => array_map(fn ($model) => $this->toDomain($model), $paginator->items()),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public function save(ModuleRecordEntity $record): ModuleRecordEntity
    {
        $data = [
            'module_id' => $record->moduleId(),
            'data' => $record->data(),
            'updated_by' => $record->updatedBy(),
        ];

        if ($record->id()) {
            // Update existing record
            $model = ModuleRecord::findOrFail($record->id());
            $model->update($data);
        } else {
            // Create new record
            $data['created_by'] = $record->createdBy();
            $model = ModuleRecord::create($data);
        }

        return $this->toDomain($model->fresh());
    }

    public function delete(int $moduleId, int $recordId): bool
    {
        $model = ModuleRecord::where('module_id', $moduleId)
            ->find($recordId);

        if (! $model) {
            return false;
        }

        return (bool) $model->delete();
    }

    public function bulkDelete(int $moduleId, array $recordIds): int
    {
        return ModuleRecord::where('module_id', $moduleId)
            ->whereIn('id', $recordIds)
            ->delete();
    }

    public function count(int $moduleId, array $filters = []): int
    {
        $query = ModuleRecord::where('module_id', $moduleId);
        $query = $this->applyFilters($query, $filters);

        return $query->count();
    }

    public function exists(int $moduleId, int $recordId): bool
    {
        return ModuleRecord::where('module_id', $moduleId)
            ->where('id', $recordId)
            ->exists();
    }

    public function findByIds(int $moduleId, array $recordIds): array
    {
        if (empty($recordIds)) {
            return [];
        }

        $models = ModuleRecord::where('module_id', $moduleId)
            ->whereIn('id', $recordIds)
            ->get();

        return array_map(fn ($model) => $this->toDomain($model), $models->all());
    }

    /**
     * Apply filters to the query based on field types and operators.
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        foreach ($filters as $fieldName => $filterConfig) {
            if (! is_array($filterConfig)) {
                continue;
            }

            $operator = $filterConfig['operator'] ?? 'equals';
            $value = $filterConfig['value'] ?? null;

            // Handle global search across multiple fields
            if ($operator === 'search' && isset($filterConfig['fields'])) {
                $searchFields = $filterConfig['fields'];
                $searchValue = mb_strtolower($value);

                $query->where(function ($q) use ($searchFields, $searchValue) {
                    foreach ($searchFields as $field) {
                        $q->orWhereRaw('LOWER(data->>?) LIKE ?', [$field, "%{$searchValue}%"]);
                    }
                });

                continue;
            }

            if ($value === null && $operator !== 'is_null' && $operator !== 'is_not_null') {
                continue;
            }

            // JSON column path for dynamic fields
            $jsonPath = "data->{$fieldName}";

            // Ensure string value for string operations
            $stringValue = is_array($value) ? '' : (string) $value;
            // Ensure array value for array operations (in, not_in)
            $arrayValue = is_array($value) ? $value : [$value];

            match ($operator) {
                'equals' => $query->whereRaw('data->>? = ?', [$fieldName, $stringValue]),
                'not_equals' => $query->whereRaw('data->>? != ?', [$fieldName, $stringValue]),
                'contains' => $query->whereRaw('LOWER(data->>?) LIKE ?', [$fieldName, '%'.mb_strtolower($stringValue).'%']),
                'not_contains' => $query->whereRaw('LOWER(data->>?) NOT LIKE ?', [$fieldName, '%'.mb_strtolower($stringValue).'%']),
                'starts_with' => $query->whereRaw('LOWER(data->>?) LIKE ?', [$fieldName, mb_strtolower($stringValue).'%']),
                'ends_with' => $query->whereRaw('LOWER(data->>?) LIKE ?', [$fieldName, '%'.mb_strtolower($stringValue)]),
                'greater_than' => $query->whereRaw('CAST(data->>? AS NUMERIC) > ?', [$fieldName, $value]),
                'less_than' => $query->whereRaw('CAST(data->>? AS NUMERIC) < ?', [$fieldName, $value]),
                'greater_than_or_equal' => $query->whereRaw('CAST(data->>? AS NUMERIC) >= ?', [$fieldName, $value]),
                'less_than_or_equal' => $query->whereRaw('CAST(data->>? AS NUMERIC) <= ?', [$fieldName, $value]),
                'between' => $query->whereRaw(
                    'CAST(data->>? AS NUMERIC) BETWEEN ? AND ?',
                    [$fieldName, $filterConfig['min'] ?? 0, $filterConfig['max'] ?? 0]
                ),
                'in' => $query->whereRaw(
                    'data->>? IN ('.implode(',', array_fill(0, count($arrayValue), '?')).')',
                    array_merge([$fieldName], $arrayValue)
                ),
                'not_in' => $query->whereRaw(
                    'data->>? NOT IN ('.implode(',', array_fill(0, count($arrayValue), '?')).')',
                    array_merge([$fieldName], $arrayValue)
                ),
                'is_null' => $query->whereNull($jsonPath),
                'is_not_null' => $query->whereNotNull($jsonPath),
                'date_equals' => $query->whereRaw('DATE(data->>?) = ?', [$fieldName, $value]),
                'date_before' => $query->whereRaw('DATE(data->>?) < ?', [$fieldName, $value]),
                'date_after' => $query->whereRaw('DATE(data->>?) > ?', [$fieldName, $value]),
                'date_between' => $query->whereRaw(
                    'DATE(data->>?) BETWEEN ? AND ?',
                    [$fieldName, $filterConfig['start'] ?? null, $filterConfig['end'] ?? null]
                ),
                default => null,
            };
        }

        return $query;
    }

    /**
     * Apply sorting to the query.
     */
    private function applySorting(Builder $query, array $sort): Builder
    {
        foreach ($sort as $fieldName => $direction) {
            // Handle direction that could be array or string
            if (is_array($direction)) {
                $direction = $direction['direction'] ?? 'asc';
            }
            $direction = mb_strtolower((string) $direction) === 'desc' ? 'DESC' : 'ASC';

            // Special handling for system fields
            if (in_array($fieldName, ['id', 'created_at', 'updated_at'])) {
                $query->orderBy($fieldName, $direction);
            } else {
                // Sort by JSON field
                $query->orderByRaw("data->>? {$direction}", [$fieldName]);
            }
        }

        return $query;
    }

    public function calculateMetric(
        int $moduleId,
        string $field,
        string $aggregation,
        array $filters = []
    ): float {
        $query = ModuleRecord::where('module_id', $moduleId);

        // Apply filters
        foreach ($filters as $filter) {
            $filterField = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? null;

            if (!$filterField) {
                continue;
            }

            $dbField = "data->{$filterField}";

            $query = match ($operator) {
                'equals' => $query->whereRaw('data->>? = ?', [$filterField, $value]),
                'not_equals' => $query->whereRaw('data->>? != ?', [$filterField, $value]),
                'contains' => $query->whereRaw('LOWER(data->>?) LIKE ?', [$filterField, "%".mb_strtolower($value)."%"]),
                'greater_than' => $query->whereRaw('CAST(data->>? AS NUMERIC) > ?', [$filterField, $value]),
                'less_than' => $query->whereRaw('CAST(data->>? AS NUMERIC) < ?', [$filterField, $value]),
                'greater_or_equal' => $query->whereRaw('CAST(data->>? AS NUMERIC) >= ?', [$filterField, $value]),
                'less_or_equal' => $query->whereRaw('CAST(data->>? AS NUMERIC) <= ?', [$filterField, $value]),
                'is_empty' => $query->whereNull($dbField),
                'is_not_empty' => $query->whereNotNull($dbField),
                default => $query,
            };
        }

        return match ($aggregation) {
            'count' => (float) $query->count(),
            'sum' => (float) $query->selectRaw('SUM(CAST(data->>? AS NUMERIC))', [$field])->value('sum') ?? 0,
            'avg' => (float) $query->selectRaw('AVG(CAST(data->>? AS NUMERIC))', [$field])->value('avg') ?? 0,
            'min' => (float) $query->selectRaw('MIN(CAST(data->>? AS NUMERIC))', [$field])->value('min') ?? 0,
            'max' => (float) $query->selectRaw('MAX(CAST(data->>? AS NUMERIC))', [$field])->value('max') ?? 0,
            'count_distinct' => (float) $query->selectRaw('COUNT(DISTINCT data->>?)', [$field])->value('count') ?? 0,
            default => (float) $query->count(),
        };
    }

    /**
     * Convert Eloquent model to domain entity.
     */
    private function toDomain(ModuleRecord $model): ModuleRecordEntity
    {
        return new ModuleRecordEntity(
            id: $model->id,
            moduleId: $model->module_id,
            data: $model->data ?? [],
            createdBy: $model->created_by,
            updatedBy: $model->updated_by,
            createdAt: new DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at->toDateTimeString()) : null,
            deletedAt: $model->deleted_at ? new DateTimeImmutable($model->deleted_at->toDateTimeString()) : null,
        );
    }
}
