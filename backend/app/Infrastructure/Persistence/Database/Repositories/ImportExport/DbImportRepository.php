<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\ImportExport;

use App\Domain\ImportExport\Entities\Import as ImportEntity;
use App\Domain\ImportExport\Repositories\ImportRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use stdClass;

class DbImportRepository implements ImportRepositoryInterface
{
    private const TABLE = 'imports';
    private const TABLE_ROWS = 'import_rows';
    private const TABLE_MODULES = 'modules';
    private const TABLE_USERS = 'users';

    private const STATUS_PENDING = 'pending';
    private const STATUS_PROCESSING = 'processing';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_FAILED = 'failed';
    private const STATUS_CANCELLED = 'cancelled';

    private const ROW_STATUS_PENDING = 'pending';
    private const ROW_STATUS_SUCCESS = 'success';
    private const ROW_STATUS_FAILED = 'failed';
    private const ROW_STATUS_SKIPPED = 'skipped';

    /**
     * Find import by ID.
     */
    public function findById(int $id): ?ImportEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    /**
     * Find import by ID as array for backward compatibility.
     */
    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->toArrayWithRelations($row) : null;
    }

    /**
     * Find import by ID with rows.
     */
    public function findByIdWithRows(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $array = $this->toArrayWithRelations($row);
        $array['rows'] = DB::table(self::TABLE_ROWS)
            ->where('import_id', $id)
            ->orderBy('row_number')
            ->get()
            ->map(fn ($r) => $this->rowToArray($r))
            ->all();

        return $array;
    }

    /**
     * List imports with filtering and pagination.
     */
    public function listImports(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        if (!empty($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['file_type'])) {
            $query->where('file_type', $filters['file_type']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortField, $sortDir);

        $total = $query->count();

        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return PaginatedResult::create(
            items: $rows->map(fn ($row) => $this->toArrayWithRelations($row))->all(),
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    /**
     * Get import rows with pagination.
     */
    public function getImportRows(int $importId, array $filters = [], int $perPage = 50, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_ROWS)->where('import_id', $importId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['has_errors'])) {
            $query->whereNotNull('errors')
                ->whereRaw("errors::text != '[]'");
        }

        $query->orderBy('row_number', 'asc');

        $total = $query->count();

        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return PaginatedResult::create(
            items: $rows->map(fn ($row) => $this->rowToArray($row))->all(),
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    /**
     * Get failed rows for an import.
     */
    public function getFailedRows(int $importId): array
    {
        $rows = DB::table(self::TABLE_ROWS)
            ->where('import_id', $importId)
            ->where('status', self::ROW_STATUS_FAILED)
            ->orderBy('row_number')
            ->get();

        return $rows->map(fn ($row) => $this->rowToArray($row))->all();
    }

    /**
     * Get import statistics.
     */
    public function getImportStats(int $importId): array
    {
        $row = DB::table(self::TABLE)->where('id', $importId)->first();

        if (!$row) {
            throw new RuntimeException("Import not found: {$importId}");
        }

        $duration = null;
        $rowsPerSecond = null;

        if ($row->started_at && $row->completed_at) {
            $started = new DateTimeImmutable($row->started_at);
            $completed = new DateTimeImmutable($row->completed_at);
            $duration = $completed->getTimestamp() - $started->getTimestamp();

            if ($row->processed_rows > 0 && $duration > 0) {
                $rowsPerSecond = round($row->processed_rows / $duration, 2);
            }
        }

        $progressPercentage = $row->total_rows > 0
            ? round(($row->processed_rows / $row->total_rows) * 100, 1)
            : 0;

        return [
            'total_rows' => (int) $row->total_rows,
            'processed_rows' => (int) $row->processed_rows,
            'successful_rows' => (int) $row->successful_rows,
            'failed_rows' => (int) $row->failed_rows,
            'skipped_rows' => (int) $row->skipped_rows,
            'progress_percentage' => $progressPercentage,
            'status' => $row->status,
            'duration' => $duration,
            'rows_per_second' => $rowsPerSecond,
        ];
    }

    /**
     * Get user's import history.
     */
    public function getUserImportHistory(int $userId, int $limit = 10): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => $this->toArrayWithRelations($row))->all();
    }

    /**
     * Create a new import.
     */
    public function create(array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->toArrayWithRelations($row);
    }

    /**
     * Update import.
     */
    public function update(int $id, array $data): array
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->toArrayWithRelations($row);
    }

    /**
     * Save an import (insert or update).
     */
    public function save(ImportEntity $import): ImportEntity
    {
        $data = $this->toRowData($import);

        if ($import->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $import->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $import->getId();
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

    /**
     * Delete import and its file.
     */
    public function delete(int $id): bool
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            throw new RuntimeException("Import not found: {$id}");
        }

        // Delete the file if it exists
        if ($row->file_path && Storage::disk('imports')->exists($row->file_path)) {
            Storage::disk('imports')->delete($row->file_path);
        }

        // Delete rows first
        DB::table(self::TABLE_ROWS)->where('import_id', $id)->delete();

        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    /**
     * Mark import as started.
     */
    public function markAsStarted(int $id): void
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'status' => self::STATUS_PROCESSING,
                'started_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Mark import as completed.
     */
    public function markAsCompleted(int $id): void
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'status' => self::STATUS_COMPLETED,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Mark import as failed.
     */
    public function markAsFailed(int $id, string $message): void
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'status' => self::STATUS_FAILED,
                'error_message' => $message,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Mark import as cancelled.
     */
    public function markAsCancelled(int $id): void
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'status' => self::STATUS_CANCELLED,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Increment processed count.
     */
    public function incrementProcessed(int $id, string $status): void
    {
        $field = match ($status) {
            'success' => 'successful_rows',
            'failed' => 'failed_rows',
            'skipped' => 'skipped_rows',
            default => null,
        };

        $updates = ['processed_rows' => DB::raw('processed_rows + 1'), 'updated_at' => now()];
        if ($field) {
            $updates[$field] = DB::raw("{$field} + 1");
        }

        DB::table(self::TABLE)->where('id', $id)->update($updates);
    }

    /**
     * Get pending rows for an import.
     */
    public function getPendingRows(int $importId): array
    {
        $rows = DB::table(self::TABLE_ROWS)
            ->where('import_id', $importId)
            ->where('status', self::ROW_STATUS_PENDING)
            ->get();

        return $rows->map(fn ($row) => $this->rowToArray($row))->all();
    }

    /**
     * Delete import rows.
     */
    public function deleteImportRows(int $importId): void
    {
        DB::table(self::TABLE_ROWS)->where('import_id', $importId)->delete();
    }

    /**
     * Get import error analysis.
     */
    public function getImportErrorAnalysis(int $importId): array
    {
        $failedRows = DB::table(self::TABLE_ROWS)
            ->where('import_id', $importId)
            ->where('status', self::ROW_STATUS_FAILED)
            ->get();

        $errorTypes = [];
        $fieldErrors = [];

        foreach ($failedRows as $row) {
            $errors = $row->errors
                ? (is_string($row->errors) ? json_decode($row->errors, true) : $row->errors)
                : [];

            foreach ($errors as $field => $messages) {
                if (!isset($fieldErrors[$field])) {
                    $fieldErrors[$field] = 0;
                }
                $fieldErrors[$field]++;

                foreach ((array) $messages as $message) {
                    $type = $this->categorizeError($message);
                    if (!isset($errorTypes[$type])) {
                        $errorTypes[$type] = 0;
                    }
                    $errorTypes[$type]++;
                }
            }
        }

        arsort($errorTypes);
        arsort($fieldErrors);

        $sampleErrors = $failedRows->take(10)->map(fn ($row) => [
            'row_number' => $row->row_number,
            'errors' => $row->errors
                ? (is_string($row->errors) ? json_decode($row->errors, true) : $row->errors)
                : [],
        ])->toArray();

        return [
            'total_failed_rows' => $failedRows->count(),
            'error_types' => $errorTypes,
            'field_errors' => $fieldErrors,
            'sample_errors' => $sampleErrors,
        ];
    }

    /**
     * Get activity summary.
     */
    public function getActivitySummary(?int $userId = null, ?string $period = 'month'): array
    {
        $dateFrom = match ($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };

        $query = DB::table(self::TABLE)->where('created_at', '>=', $dateFrom);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', self::STATUS_COMPLETED)->count();
        $failed = (clone $query)->where('status', self::STATUS_FAILED)->count();
        $totalRecords = (clone $query)->sum('successful_rows');

        return [
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'total_records' => (int) $totalRecords,
        ];
    }

    /**
     * Convert Import row to array with relations.
     */
    private function toArrayWithRelations(stdClass $row): array
    {
        $data = (array) $row;

        // Decode JSON fields
        if (isset($data['column_mapping']) && is_string($data['column_mapping'])) {
            $data['column_mapping'] = json_decode($data['column_mapping'], true);
        }
        if (isset($data['settings']) && is_string($data['settings'])) {
            $data['settings'] = json_decode($data['settings'], true);
        }

        // Load module relation
        if ($row->module_id) {
            $module = DB::table(self::TABLE_MODULES)->where('id', $row->module_id)->first();
            $data['module'] = $module ? ['id' => $module->id, 'name' => $module->name] : null;
        }

        // Load user relation
        if ($row->user_id) {
            $user = DB::table(self::TABLE_USERS)->where('id', $row->user_id)->first();
            $data['user'] = $user ? ['id' => $user->id, 'name' => $user->name] : null;
        }

        return $data;
    }

    /**
     * Convert ImportRow to array.
     */
    private function rowToArray(stdClass $row): array
    {
        $data = (array) $row;

        // Decode JSON fields
        if (isset($data['data']) && is_string($data['data'])) {
            $data['data'] = json_decode($data['data'], true);
        }
        if (isset($data['errors']) && is_string($data['errors'])) {
            $data['errors'] = json_decode($data['errors'], true);
        }

        return $data;
    }

    /**
     * Convert an Database model to a domain entity.
     */
    private function toDomainEntity(stdClass $row): ImportEntity
    {
        return ImportEntity::reconstitute(
            id: (int) $row->id,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(ImportEntity $import): array
    {
        return [
            // The Import entity is minimal, so we only have timestamps
            // In a more complete implementation, this would include all entity properties
        ];
    }

    /**
     * Categorize error message.
     */
    private function categorizeError(string $message): string
    {
        $message = strtolower($message);

        if (str_contains($message, 'required')) {
            return 'Missing Required Field';
        }
        if (str_contains($message, 'format') || str_contains($message, 'invalid')) {
            return 'Invalid Format';
        }
        if (str_contains($message, 'duplicate')) {
            return 'Duplicate';
        }
        if (str_contains($message, 'reference') || str_contains($message, 'not found')) {
            return 'Invalid Reference';
        }

        return 'Other';
    }
}
