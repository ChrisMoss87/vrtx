<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Modules\Entities\ModuleRecord;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleRecordModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final class EloquentModuleRecordRepository implements ModuleRecordRepositoryInterface
{
    public function findById(int $moduleId, int $recordId): ?ModuleRecord
    {
        $model = ModuleRecordModel::where('module_id', $moduleId)
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
        $query = ModuleRecordModel::where('module_id', $moduleId);

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

    public function save(ModuleRecord $record): ModuleRecord
    {
        $data = [
            'module_id' => $record->moduleId(),
            'data' => $record->data(),
            'updated_by' => $record->updatedBy(),
        ];

        if ($record->id()) {
            // Update existing record
            $model = ModuleRecordModel::findOrFail($record->id());
            $model->update($data);
        } else {
            // Create new record
            $data['created_by'] = $record->createdBy();
            $model = ModuleRecordModel::create($data);
        }

        return $this->toDomain($model->fresh());
    }

    public function delete(int $moduleId, int $recordId): bool
    {
        $model = ModuleRecordModel::where('module_id', $moduleId)
            ->find($recordId);

        if (! $model) {
            return false;
        }

        return (bool) $model->delete();
    }

    public function bulkDelete(int $moduleId, array $recordIds): int
    {
        return ModuleRecordModel::where('module_id', $moduleId)
            ->whereIn('id', $recordIds)
            ->delete();
    }

    public function count(int $moduleId, array $filters = []): int
    {
        $query = ModuleRecordModel::where('module_id', $moduleId);
        $query = $this->applyFilters($query, $filters);

        return $query->count();
    }

    public function exists(int $moduleId, int $recordId): bool
    {
        return ModuleRecordModel::where('module_id', $moduleId)
            ->where('id', $recordId)
            ->exists();
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

            match ($operator) {
                'equals' => $query->whereJsonContains($jsonPath, $value),
                'not_equals' => $query->whereJsonDoesntContain($jsonPath, $value),
                'contains' => $query->whereRaw('LOWER(data->>?) LIKE ?', [$fieldName, '%'.mb_strtolower($value).'%']),
                'not_contains' => $query->whereRaw('LOWER(data->>?) NOT LIKE ?', [$fieldName, '%'.mb_strtolower($value).'%']),
                'starts_with' => $query->whereRaw('LOWER(data->>?) LIKE ?', [$fieldName, mb_strtolower($value).'%']),
                'ends_with' => $query->whereRaw('LOWER(data->>?) LIKE ?', [$fieldName, '%'.mb_strtolower($value)]),
                'greater_than' => $query->whereRaw('CAST(data->>? AS NUMERIC) > ?', [$fieldName, $value]),
                'less_than' => $query->whereRaw('CAST(data->>? AS NUMERIC) < ?', [$fieldName, $value]),
                'greater_than_or_equal' => $query->whereRaw('CAST(data->>? AS NUMERIC) >= ?', [$fieldName, $value]),
                'less_than_or_equal' => $query->whereRaw('CAST(data->>? AS NUMERIC) <= ?', [$fieldName, $value]),
                'between' => $query->whereRaw(
                    'CAST(data->>? AS NUMERIC) BETWEEN ? AND ?',
                    [$fieldName, $filterConfig['min'] ?? 0, $filterConfig['max'] ?? 0]
                ),
                'in' => $query->whereRaw(
                    'data->>? IN ('.implode(',', array_fill(0, count($value), '?')).')',
                    array_merge([$fieldName], $value)
                ),
                'not_in' => $query->whereRaw(
                    'data->>? NOT IN ('.implode(',', array_fill(0, count($value), '?')).')',
                    array_merge([$fieldName], $value)
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
            $direction = mb_strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

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

    /**
     * Convert Eloquent model to domain entity.
     */
    private function toDomain(ModuleRecordModel $model): ModuleRecord
    {
        return new ModuleRecord(
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
