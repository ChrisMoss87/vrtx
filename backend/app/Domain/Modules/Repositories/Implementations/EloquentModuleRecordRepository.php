<?php

declare(strict_types=1);

namespace App\Domain\Modules\Repositories\Implementations;

use App\Domain\Modules\Entities\ModuleRecord as ModuleRecordEntity;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Models\ModuleRecord as ModuleRecordModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class EloquentModuleRecordRepository implements ModuleRecordRepositoryInterface
{
    public function findById(int $moduleId, int $recordId): ?ModuleRecordEntity
    {
        $model = ModuleRecordModel::where('module_id', $moduleId)
            ->find($recordId);

        return $model ? $this->toEntity($model) : null;
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
        foreach ($filters as $field => $filterConfig) {
            $this->applyFilter($query, $field, $filterConfig);
        }

        // Get total count before pagination
        $total = $query->count();

        // Apply sorting
        foreach ($sort as $field => $direction) {
            $query->orderByRaw("data->>? {$direction}", [$field]);
        }

        // Apply pagination
        $records = $query->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'data' => $records->map(fn ($model) => $this->toEntity($model))->all(),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    public function save(ModuleRecordEntity $record): ModuleRecordEntity
    {
        $data = [
            'module_id' => $record->moduleId(),
            'data' => $record->data(),
            'updated_by' => auth()->id(),
        ];

        if ($record->id()) {
            $model = ModuleRecordModel::findOrFail($record->id());
            $model->update($data);
        } else {
            $data['created_by'] = auth()->id();
            $model = ModuleRecordModel::create($data);
        }

        return $this->toEntity($model);
    }

    public function delete(int $moduleId, int $recordId): bool
    {
        $model = ModuleRecordModel::where('module_id', $moduleId)
            ->find($recordId);

        if (!$model) {
            return false;
        }

        return $model->delete();
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

        foreach ($filters as $field => $filterConfig) {
            $this->applyFilter($query, $field, $filterConfig);
        }

        return $query->count();
    }

    public function exists(int $moduleId, int $recordId): bool
    {
        return ModuleRecordModel::where('module_id', $moduleId)
            ->where('id', $recordId)
            ->exists();
    }

    private function toEntity(ModuleRecordModel $model): ModuleRecordEntity
    {
        return new ModuleRecordEntity(
            id: $model->id,
            moduleId: $model->module_id,
            data: $model->data ?? [],
            createdBy: $model->created_by,
            updatedBy: $model->updated_by,
            createdAt: new DateTimeImmutable($model->created_at),
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at) : null,
            deletedAt: $model->deleted_at ? new DateTimeImmutable($model->deleted_at) : null,
        );
    }

    private function applyFilter($query, string $field, array $filterConfig): void
    {
        $operator = $filterConfig['operator'] ?? 'eq';
        $value = $filterConfig['value'] ?? null;

        // Escape LIKE pattern special characters to prevent pattern injection
        $escapeLikePattern = function (string $val): string {
            return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $val);
        };

        match ($operator) {
            'eq' => $query->whereRaw("data->>? = ?", [$field, $value]),
            'neq' => $query->whereRaw("data->>? != ?", [$field, $value]),
            'gt' => $query->whereRaw("(data->>?)::numeric > ?", [$field, $value]),
            'gte' => $query->whereRaw("(data->>?)::numeric >= ?", [$field, $value]),
            'lt' => $query->whereRaw("(data->>?)::numeric < ?", [$field, $value]),
            'lte' => $query->whereRaw("(data->>?)::numeric <= ?", [$field, $value]),
            'contains' => $query->whereRaw("data->>? ILIKE ?", [$field, '%' . $escapeLikePattern((string)$value) . '%']),
            'starts' => $query->whereRaw("data->>? ILIKE ?", [$field, $escapeLikePattern((string)$value) . '%']),
            'ends' => $query->whereRaw("data->>? ILIKE ?", [$field, '%' . $escapeLikePattern((string)$value)]),
            'in' => $this->applyInFilter($query, $field, (array)$value),
            'not_in' => $this->applyNotInFilter($query, $field, (array)$value),
            'null' => $query->whereRaw("data->>? IS NULL", [$field]),
            'not_null' => $query->whereRaw("data->>? IS NOT NULL", [$field]),
            'between' => $query->whereRaw("(data->>?)::numeric BETWEEN ? AND ?", [$field, $value[0], $value[1]]),
            default => $query->whereRaw("data->>? = ?", [$field, $value]),
        };
    }

    /**
     * Apply IN filter with proper parameterization to prevent SQL injection
     */
    private function applyInFilter($query, string $field, array $values): void
    {
        if (empty($values)) {
            $query->whereRaw('1 = 0'); // No match if empty array
            return;
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $query->whereRaw("data->>? IN ($placeholders)", array_merge([$field], $values));
    }

    /**
     * Apply NOT IN filter with proper parameterization to prevent SQL injection
     */
    private function applyNotInFilter($query, string $field, array $values): void
    {
        if (empty($values)) {
            return; // All values match if nothing to exclude
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $query->whereRaw("data->>? NOT IN ($placeholders)", array_merge([$field], $values));
    }
}
