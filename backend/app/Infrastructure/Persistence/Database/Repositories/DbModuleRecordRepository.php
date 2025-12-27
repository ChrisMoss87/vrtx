<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories;

use App\Domain\Modules\Entities\ModuleRecord as ModuleRecordEntity;
use App\Domain\Modules\Events\ModuleRecordCreated;
use App\Domain\Modules\Events\ModuleRecordDeleted;
use App\Domain\Modules\Events\ModuleRecordUpdated;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DbModuleRecordRepository implements ModuleRecordRepositoryInterface
{
    private const TABLE = 'module_records';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AuthContextInterface $authContext,
    ) {}

    public function findById(int $moduleId, int $recordId): ?ModuleRecordEntity
    {
        $row = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('id', $recordId)
            ->whereNull('deleted_at')
            ->first();

        return $row ? $this->toDomain($row) : null;
    }

    public function findAll(
        int $moduleId,
        array $filters = [],
        array $sort = [],
        int $page = 1,
        int $perPage = 15
    ): array {
        $query = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->whereNull('deleted_at');

        // Apply filters
        $query = $this->applyFilters($query, $filters);

        // Apply sorting
        $query = $this->applySorting($query, $sort);

        // Get total count before pagination
        $total = $query->count();

        // Get paginated results
        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $lastPage = (int) ceil($total / $perPage);

        return [
            'data' => array_map(fn($row) => $this->toDomain($row), $rows->all()),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
        ];
    }

    public function save(ModuleRecordEntity $record): ModuleRecordEntity
    {
        $isNew = !$record->id();
        $oldData = [];

        $data = [
            'module_id' => $record->moduleId(),
            'data' => json_encode($record->data()),
            'updated_by' => $record->updatedBy(),
        ];

        if ($record->id()) {
            // Get old data for update event
            $oldRow = DB::table(self::TABLE)->where('id', $record->id())->first();
            $oldData = $oldRow ? (is_string($oldRow->data) ? json_decode($oldRow->data, true) : $oldRow->data) : [];

            // Update existing record
            DB::table(self::TABLE)
                ->where('id', $record->id())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $record->id();
        } else {
            // Create new record
            $data['created_by'] = $record->createdBy();
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $savedRecord = $this->findById($record->moduleId(), $id);

        // Dispatch domain events
        if ($savedRecord !== null) {
            if ($isNew) {
                $this->eventDispatcher->dispatch(new ModuleRecordCreated(
                    recordId: $savedRecord->id(),
                    moduleId: $savedRecord->moduleId(),
                    data: $savedRecord->data(),
                    createdBy: $this->authContext->userId(),
                ));
            } else {
                $this->eventDispatcher->dispatch(new ModuleRecordUpdated(
                    recordId: $savedRecord->id(),
                    moduleId: $savedRecord->moduleId(),
                    oldData: $oldData,
                    newData: $savedRecord->data(),
                    updatedBy: $this->authContext->userId(),
                ));
            }
        }

        return $savedRecord;
    }

    public function delete(int $moduleId, int $recordId): bool
    {
        // Get record data before deletion for event
        $row = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('id', $recordId)
            ->first();

        $deleted = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('id', $recordId)
            ->update(['deleted_at' => now()]) > 0;

        // Dispatch domain event after successful deletion
        if ($deleted && $row !== null) {
            $data = is_string($row->data) ? json_decode($row->data, true) : ($row->data ?? []);
            $this->eventDispatcher->dispatch(new ModuleRecordDeleted(
                recordId: (int) $row->id,
                moduleId: (int) $row->module_id,
                data: $data,
                deletedBy: $this->authContext->userId(),
            ));
        }

        return $deleted;
    }

    public function bulkDelete(int $moduleId, array $recordIds): int
    {
        // Get records data before deletion for events
        $rows = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->whereIn('id', $recordIds)
            ->get();

        $count = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->whereIn('id', $recordIds)
            ->update(['deleted_at' => now()]);

        // Dispatch domain events for each deleted record
        foreach ($rows as $row) {
            $data = is_string($row->data) ? json_decode($row->data, true) : ($row->data ?? []);
            $this->eventDispatcher->dispatch(new ModuleRecordDeleted(
                recordId: (int) $row->id,
                moduleId: (int) $row->module_id,
                data: $data,
                deletedBy: $this->authContext->userId(),
            ));
        }

        return $count;
    }

    public function count(int $moduleId, array $filters = []): int
    {
        $query = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->whereNull('deleted_at');
        $query = $this->applyFilters($query, $filters);

        return $query->count();
    }

    public function exists(int $moduleId, int $recordId): bool
    {
        return DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('id', $recordId)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function findByIds(int $moduleId, array $recordIds): array
    {
        if (empty($recordIds)) {
            return [];
        }

        $rows = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->whereIn('id', $recordIds)
            ->whereNull('deleted_at')
            ->get();

        return array_map(fn($row) => $this->toDomain($row), $rows->all());
    }

    /**
     * Apply filters to the query based on field types and operators.
     */
    private function applyFilters($query, array $filters)
    {
        foreach ($filters as $fieldName => $filterConfig) {
            if (!is_array($filterConfig)) {
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
                'contains' => $query->whereRaw('LOWER(data->>?) LIKE ?', [$fieldName, '%' . mb_strtolower($stringValue) . '%']),
                'not_contains' => $query->whereRaw('LOWER(data->>?) NOT LIKE ?', [$fieldName, '%' . mb_strtolower($stringValue) . '%']),
                'starts_with' => $query->whereRaw('LOWER(data->>?) LIKE ?', [$fieldName, mb_strtolower($stringValue) . '%']),
                'ends_with' => $query->whereRaw('LOWER(data->>?) LIKE ?', [$fieldName, '%' . mb_strtolower($stringValue)]),
                'greater_than' => $query->whereRaw('CAST(data->>? AS NUMERIC) > ?', [$fieldName, $value]),
                'less_than' => $query->whereRaw('CAST(data->>? AS NUMERIC) < ?', [$fieldName, $value]),
                'greater_than_or_equal' => $query->whereRaw('CAST(data->>? AS NUMERIC) >= ?', [$fieldName, $value]),
                'less_than_or_equal' => $query->whereRaw('CAST(data->>? AS NUMERIC) <= ?', [$fieldName, $value]),
                'between' => $query->whereRaw(
                    'CAST(data->>? AS NUMERIC) BETWEEN ? AND ?',
                    [$fieldName, $filterConfig['min'] ?? 0, $filterConfig['max'] ?? 0]
                ),
                'in' => $query->whereRaw(
                    'data->>? IN (' . implode(',', array_fill(0, count($arrayValue), '?')) . ')',
                    array_merge([$fieldName], $arrayValue)
                ),
                'not_in' => $query->whereRaw(
                    'data->>? NOT IN (' . implode(',', array_fill(0, count($arrayValue), '?')) . ')',
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
    private function applySorting($query, array $sort)
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

    public function findByPeriod(
        int $moduleId,
        ?DateTimeImmutable $periodStart = null,
        ?DateTimeImmutable $periodEnd = null,
        ?int $userId = null
    ): array {
        $query = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->whereNull('deleted_at');

        if ($periodStart !== null) {
            $query->where('created_at', '>=', $periodStart->format('Y-m-d H:i:s'));
        }

        if ($periodEnd !== null) {
            $query->where('created_at', '<=', $periodEnd->format('Y-m-d H:i:s'));
        }

        if ($userId !== null) {
            $query->where('created_by', $userId);
        }

        $rows = $query->orderBy('created_at', 'desc')->get();

        return array_map(fn($row) => $this->toDomain($row), $rows->all());
    }

    public function calculateMetric(
        int $moduleId,
        string $field,
        string $aggregation,
        array $filters = []
    ): float {
        $query = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->whereNull('deleted_at');

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
                'contains' => $query->whereRaw('LOWER(data->>?) LIKE ?', [$filterField, "%" . mb_strtolower($value) . "%"]),
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

    public function findByIdAsArray(int $recordId): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('id', $recordId)
            ->whereNull('deleted_at')
            ->first();

        if (!$row) {
            return null;
        }

        return $this->rowToArray($row);
    }

    public function update(int $recordId, array $data): ?array
    {
        $exists = DB::table(self::TABLE)
            ->where('id', $recordId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$exists) {
            return null;
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['data'])) {
            $updateData['data'] = json_encode($data['data']);
        }

        foreach ($data as $key => $value) {
            if ($key !== 'data' && in_array($key, ['owner_id', 'assigned_to', 'updated_by'])) {
                $updateData[$key] = $value;
            }
        }

        DB::table(self::TABLE)
            ->where('id', $recordId)
            ->update($updateData);

        return $this->findByIdAsArray($recordId);
    }

    public function findByModuleId(int $moduleId, ?int $limit = null): array
    {
        $query = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->map(fn($row) => $this->rowToArray($row))->toArray();
    }

    public function findMatchingRecords(
        int $moduleId,
        int $excludeRecordId,
        string $field,
        mixed $value,
        string $matchType = 'exact'
    ): array {
        $query = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('id', '!=', $excludeRecordId)
            ->whereNull('deleted_at');

        $query = match ($matchType) {
            'exact' => $query->whereRaw("data->>? = ?", [$field, $value]),
            'fuzzy' => $query->whereRaw("data->>? ILIKE ?", [$field, "%{$value}%"]),
            'email_domain' => $query->whereRaw("data->>? ILIKE ?", [$field, "%@" . substr(strrchr((string) $value, "@"), 1)]),
            default => $query->whereRaw("data->>? = ?", [$field, $value]),
        };

        return $query->limit(100)->get()->map(fn($row) => $this->rowToArray($row))->toArray();
    }

    private function rowToArray(stdClass $row): array
    {
        return [
            'id' => (int) $row->id,
            'module_id' => (int) $row->module_id,
            'data' => $row->data ? (is_string($row->data) ? json_decode($row->data, true) : $row->data) : [],
            'owner_id' => $row->owner_id ? (int) $row->owner_id : null,
            'assigned_to' => $row->assigned_to ? (int) $row->assigned_to : null,
            'created_by' => $row->created_by ? (int) $row->created_by : null,
            'updated_by' => $row->updated_by ? (int) $row->updated_by : null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Convert database row to domain entity.
     */
    private function toDomain(stdClass $row): ModuleRecordEntity
    {
        return new ModuleRecordEntity(
            id: (int) $row->id,
            moduleId: (int) $row->module_id,
            data: $row->data ? (is_string($row->data) ? json_decode($row->data, true) : $row->data) : [],
            createdBy: $row->created_by ? (int) $row->created_by : null,
            updatedBy: $row->updated_by ? (int) $row->updated_by : null,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
            deletedAt: $row->deleted_at ? new DateTimeImmutable($row->deleted_at) : null,
        );
    }
}
