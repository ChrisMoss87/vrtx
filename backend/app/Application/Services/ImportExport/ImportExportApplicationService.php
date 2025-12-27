<?php

declare(strict_types=1);

namespace App\Application\Services\ImportExport;

use App\Domain\ImportExport\Repositories\ImportRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportExportApplicationService
{
    /**
     * System fields that are stored directly on the table.
     */
    private const SYSTEM_FIELDS = ['id', 'module_id', 'created_at', 'updated_at', 'owner_id', 'created_by'];

    /**
     * Valid operators for filtering.
     */
    private const VALID_OPERATORS = ['=', '!=', '<', '>', '<=', '>=', 'ILIKE', 'NOT ILIKE', 'IS NULL', 'IS NOT NULL'];

    /**
     * Valid sort directions.
     */
    private const VALID_DIRECTIONS = ['asc', 'desc', 'ASC', 'DESC'];

    public function __construct(
        private ImportRepositoryInterface $importRepository,
        private AuthContextInterface $authContext,
        private ModuleRepositoryInterface $moduleRepository,
        private ModuleRecordRepositoryInterface $moduleRecordRepository,
    ) {}

    /**
     * Validate field name to prevent SQL injection.
     * Only allows alphanumeric characters and underscores.
     *
     * @throws \InvalidArgumentException if field name is invalid
     */
    private function validateFieldName(string $field): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
            throw new \InvalidArgumentException("Invalid field name: {$field}");
        }

        return $field;
    }

    /**
     * Validate operator to prevent SQL injection.
     */
    private function validateOperator(string $operator): string
    {
        $normalizedOperator = strtoupper(trim($operator));

        // Map common operators
        $operatorMap = [
            '=' => '=',
            '!=' => '!=',
            '<>' => '!=',
            '<' => '<',
            '>' => '>',
            '<=' => '<=',
            '>=' => '>=',
            'LIKE' => 'ILIKE',
            'ILIKE' => 'ILIKE',
            'NOT LIKE' => 'NOT ILIKE',
            'NOT ILIKE' => 'NOT ILIKE',
        ];

        if (isset($operatorMap[$normalizedOperator])) {
            return $operatorMap[$normalizedOperator];
        }

        if (in_array($normalizedOperator, self::VALID_OPERATORS, true)) {
            return $normalizedOperator;
        }

        // Default to equals for invalid operators
        return '=';
    }

    /**
     * Validate sort direction.
     */
    private function validateDirection(string $direction): string
    {
        $normalizedDirection = strtolower(trim($direction));

        return in_array($normalizedDirection, ['asc', 'desc'], true) ? $normalizedDirection : 'asc';
    }

    /**
     * Get safe column expression for JSONB data field.
     */
    private function getSafeColumnExpression(string $field): string
    {
        $field = $this->validateFieldName($field);

        if (in_array($field, self::SYSTEM_FIELDS, true)) {
            return $field;
        }

        return "data->>'{$field}'";
    }

    // ==========================================
    // IMPORT QUERY USE CASES
    // ==========================================

    /**
     * List imports with filtering and pagination.
     */
    public function listImports(array $filters = [], int $perPage = 15): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->importRepository->listImports($filters, $perPage, $page);
    }

    /**
     * Get a single import by ID.
     */
    public function getImport(int $id): ?array
    {
        return $this->importRepository->findById($id);
    }

    /**
     * Get import with all rows.
     */
    public function getImportWithRows(int $id): ?array
    {
        return $this->importRepository->findByIdWithRows($id);
    }

    /**
     * Get import rows with pagination.
     */
    public function getImportRows(int $importId, array $filters = [], int $perPage = 50): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->importRepository->getImportRows($importId, $filters, $perPage, $page);
    }

    /**
     * Get failed rows for an import.
     */
    public function getFailedRows(int $importId): array
    {
        return $this->importRepository->getFailedRows($importId);
    }

    /**
     * Get import statistics.
     */
    public function getImportStats(int $importId): array
    {
        return $this->importRepository->getImportStats($importId);
    }

    /**
     * Get user's import history.
     */
    public function getUserImportHistory(int $userId, int $limit = 10): array
    {
        return $this->importRepository->getUserImportHistory($userId, $limit);
    }

    /**
     * Preview import file columns.
     */
    public function previewFileColumns(string $filePath, string $fileType): array
    {
        $columns = [];
        $sampleRows = [];

        if ($fileType === Import::FILE_TYPE_CSV) {
            $handle = fopen(Storage::disk('imports')->path($filePath), 'r');
            if ($handle) {
                $headers = fgetcsv($handle);
                $columns = $headers ?: [];

                $rowCount = 0;
                while (($row = fgetcsv($handle)) !== false && $rowCount < 5) {
                    $sampleRows[] = array_combine($columns, $row);
                    $rowCount++;
                }
                fclose($handle);
            }
        }
        // Add XLSX parsing if needed

        return [
            'columns' => $columns,
            'sample_rows' => $sampleRows,
            'total_preview_rows' => count($sampleRows),
        ];
    }

    /**
     * Get available fields for import mapping.
     */
    public function getModuleImportFields(int $moduleId): array
    {
        $module = Module::with('fields')->findOrFail($moduleId);

        return $module->fields
            ->filter(fn ($field) => !in_array($field->api_name, ['id', 'created_at', 'updated_at', 'deleted_at']))
            ->map(fn ($field) => [
                'api_name' => $field->api_name,
                'label' => $field->label,
                'type' => $field->type,
                'required' => $field->is_required,
            ])
            ->values()
            ->toArray();
    }

    // ==========================================
    // IMPORT COMMAND USE CASES
    // ==========================================

    /**
     * Create a new import.
     */
    public function createImport(array $data): array
    {
        return $this->importRepository->create([
            'module_id' => $data['module_id'],
            'user_id' => $this->authContext->userId(),
            'name' => $data['name'] ?? 'Import ' . now()->format('Y-m-d H:i'),
            'file_name' => $data['file_name'],
            'file_path' => $data['file_path'],
            'file_type' => $data['file_type'],
            'file_size' => $data['file_size'] ?? 0,
            'column_mapping' => $data['column_mapping'] ?? [],
            'import_options' => $data['import_options'] ?? [
                'duplicate_handling' => Import::DUPLICATE_SKIP,
                'duplicate_check_field' => null,
            ],
            'field_transformations' => $data['field_transformations'] ?? [],
        ]);
    }

    /**
     * Update import settings (before starting).
     */
    public function updateImportSettings(int $id, array $data): array
    {
        $import = $this->importRepository->findById($id);

        if (!$import) {
            throw new \InvalidArgumentException('Import not found');
        }

        if ($import['status'] !== Import::STATUS_PENDING) {
            throw new \InvalidArgumentException('Cannot update settings for an import that has already started');
        }

        return $this->importRepository->update($id, [
            'name' => $data['name'] ?? $import['name'],
            'column_mapping' => $data['column_mapping'] ?? $import['column_mapping'],
            'import_options' => $data['import_options'] ?? $import['import_options'],
            'field_transformations' => $data['field_transformations'] ?? $import['field_transformations'],
        ]);
    }

    /**
     * Validate import data before processing.
     */
    public function validateImport(int $id): array
    {
        $import = DB::table('imports')->where('id', $id)->first();
        $import->update(['status' => Import::STATUS_VALIDATING]);

        $errors = [];
        $rowCount = 0;

        try {
            $filePath = Storage::disk('imports')->path($import->file_path);
            $handle = fopen($filePath, 'r');

            if (!$handle) {
                throw new \RuntimeException('Cannot open import file');
            }

            $headers = fgetcsv($handle);
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $rowCount++;
                $rowData = array_combine($headers, $row);
                $mappedData = $this->mapRowData($rowData, $import->column_mapping);
                $rowErrors = $this->validateRowData($mappedData, $import->module_id);

                DB::table('import_rows')->insertGetId([
                    'import_id' => $import->id,
                    'row_number' => $rowNumber,
                    'original_data' => $rowData,
                    'mapped_data' => $mappedData,
                    'status' => empty($rowErrors) ? ImportRow::STATUS_PENDING : ImportRow::STATUS_FAILED,
                    'errors' => $rowErrors,
                ]);

                if (!empty($rowErrors)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => $rowErrors,
                    ];
                }
            }

            fclose($handle);

            $import->update([
                'status' => Import::STATUS_VALIDATED,
                'total_rows' => $rowCount,
                'validation_errors' => $errors,
            ]);

        } catch (\Exception $e) {
            $import->markAsFailed($e->getMessage());
            throw $e;
        }

        return [
            'total_rows' => $rowCount,
            'valid_rows' => $rowCount - count($errors),
            'invalid_rows' => count($errors),
            'errors' => array_slice($errors, 0, 100), // Limit error details
        ];
    }

    /**
     * Start processing the import.
     */
    public function startImport(int $id): array
    {
        $import = $this->importRepository->findById($id);

        if (!$import) {
            throw new \InvalidArgumentException('Import not found');
        }

        if (!in_array($import['status'], [Import::STATUS_PENDING, Import::STATUS_VALIDATED])) {
            throw new \InvalidArgumentException('Import is not in a valid state to start');
        }

        $this->importRepository->markAsStarted($id);

        // In production, this would dispatch a job
        // For now, process synchronously
        $this->processImport($id);

        return $this->importRepository->findById($id);
    }

    /**
     * Process import rows.
     */
    public function processImport(int $importId): void
    {
        DB::beginTransaction();

        try {
            $import = $this->importRepository->findById($importId);
            if (!$import) {
                throw new \InvalidArgumentException('Import not found');
            }

            $rows = $this->importRepository->getPendingRows($importId);

            foreach ($rows as $row) {
                try {
                    $recordId = $this->processImportRow($import, $row);

                    // Mark row as success using Database directly (repository doesn't manage ImportRow state)
                    $rowModel = ImportRow::find($row['id']);
                    $rowModel?->markAsSuccess($recordId);

                    $this->importRepository->incrementProcessed($importId, ImportRow::STATUS_SUCCESS);
                } catch (\Exception $e) {
                    // Mark row as failed using Database directly
                    $rowModel = ImportRow::find($row['id']);
                    $rowModel?->markAsFailed(['exception' => $e->getMessage()]);

                    $this->importRepository->incrementProcessed($importId, ImportRow::STATUS_FAILED);
                }
            }

            $this->importRepository->markAsCompleted($importId);
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            $this->importRepository->markAsFailed($importId, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancel an import.
     */
    public function cancelImport(int $id): array
    {
        $import = $this->importRepository->findById($id);

        if (!$import) {
            throw new \InvalidArgumentException('Import not found');
        }

        // Check if can be cancelled (not in terminal state)
        $terminalStates = [Import::STATUS_COMPLETED, Import::STATUS_FAILED, Import::STATUS_CANCELLED];
        if (in_array($import['status'], $terminalStates)) {
            throw new \InvalidArgumentException('This import cannot be cancelled');
        }

        $this->importRepository->markAsCancelled($id);

        return $this->importRepository->findById($id);
    }

    /**
     * Delete an import and its rows.
     */
    public function deleteImport(int $id): void
    {
        $this->importRepository->delete($id);
    }

    /**
     * Retry failed rows.
     */
    public function retryFailedRows(int $importId): array
    {
        $import = DB::table('imports')->where('id', $importId)->first();
        $failedRows = $import->failedRows()->get();

        $retried = 0;
        $success = 0;

        foreach ($failedRows as $row) {
            $retried++;
            try {
                $row->update(['status' => ImportRow::STATUS_PENDING, 'errors' => null]);
                $recordId = $this->processImportRow($import, $row->fresh());
                $row->markAsSuccess($recordId);
                $import->decrement('failed_rows');
                $import->increment('successful_rows');
                $success++;
            } catch (\Exception $e) {
                $row->markAsFailed(['exception' => $e->getMessage()]);
            }
        }

        return [
            'retried' => $retried,
            'success' => $success,
            'still_failed' => $retried - $success,
        ];
    }

    // ==========================================
    // EXPORT QUERY USE CASES
    // ==========================================

    /**
     * List exports with filtering and pagination.
     */
    public function listExports(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DB::table('exports')
            ->with(['module', 'user']);

        if (!empty($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (!empty($filters['file_type'])) {
            $query->where('file_type', $filters['file_type']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        if (!empty($filters['downloadable_only'])) {
            $query->where('status', Export::STATUS_COMPLETED)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });
        }

        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortField, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a single export by ID.
     */
    public function getExport(int $id): ?Export
    {
        return Export::with(['module', 'user'])->find($id);
    }

    /**
     * Get export statistics.
     */
    public function getExportStats(int $exportId): array
    {
        $export = DB::table('exports')->where('id', $exportId)->first();

        return [
            'total_records' => $export->total_records,
            'exported_records' => $export->exported_records,
            'progress_percentage' => $export->getProgressPercentage(),
            'status' => $export->status,
            'file_size' => $export->file_size,
            'file_size_formatted' => $this->formatBytes($export->file_size ?? 0),
            'download_count' => $export->download_count,
            'is_downloadable' => $export->isDownloadable(),
            'has_expired' => $export->hasExpired(),
            'expires_at' => $export->expires_at?->toIso8601String(),
            'duration' => $export->started_at && $export->completed_at
                ? $export->completed_at->diffInSeconds($export->started_at)
                : null,
        ];
    }

    /**
     * Get user's export history.
     */
    public function getUserExportHistory(int $userId, int $limit = 10): Collection
    {
        return DB::table('exports')->where('user_id', $userId)
            ->with('module')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get available fields for export.
     */
    public function getModuleExportFields(int $moduleId): array
    {
        $module = Module::with('fields')->findOrFail($moduleId);

        return $module->fields
            ->map(fn ($field) => [
                'api_name' => $field->api_name,
                'label' => $field->label,
                'type' => $field->type,
            ])
            ->values()
            ->toArray();
    }

    // ==========================================
    // EXPORT COMMAND USE CASES
    // ==========================================

    /**
     * Create and start an export.
     */
    public function createExport(array $data): Export
    {
        $export = DB::table('exports')->insertGetId([
            'module_id' => $data['module_id'],
            'user_id' => Auth::id(),
            'name' => $data['name'] ?? 'Export ' . now()->format('Y-m-d H:i'),
            'file_type' => $data['file_type'] ?? Export::FILE_TYPE_CSV,
            'selected_fields' => $data['selected_fields'] ?? [],
            'filters' => $data['filters'] ?? [],
            'sorting' => $data['sorting'] ?? [],
            'export_options' => $data['export_options'] ?? [],
        ]);

        // Count total records
        $totalRecords = $this->countExportRecords($export);
        $export->update(['total_records' => $totalRecords]);

        return $export;
    }

    /**
     * Start processing the export.
     */
    public function startExport(int $id): Export
    {
        $export = DB::table('exports')->where('id', $id)->first();

        if ($export->status !== Export::STATUS_PENDING) {
            throw new \InvalidArgumentException('Export has already been started');
        }

        $export->markAsStarted();

        // In production, this would dispatch a job
        $this->processExport($export);

        return $export->fresh();
    }

    /**
     * Process export and generate file.
     */
    public function processExport(Export $export): void
    {
        try {
            $module = Module::findOrFail($export->module_id);
            $records = $this->getExportRecords($export);

            $fileName = $this->generateExportFileName($module, $export->file_type);
            $filePath = $this->generateExportFile($export, $records, $fileName);

            $fileSize = Storage::disk('exports')->size($filePath);
            $export->markAsCompleted($filePath, $fileName, $fileSize, $records->count());

        } catch (\Exception $e) {
            $export->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Download an export file.
     */
    public function downloadExport(int $id): array
    {
        $export = DB::table('exports')->where('id', $id)->first();

        if (!$export->isDownloadable()) {
            throw new \InvalidArgumentException('Export is not available for download');
        }

        $export->incrementDownloadCount();

        return [
            'path' => Storage::disk('exports')->path($export->file_path),
            'name' => $export->file_name,
            'mime_type' => $this->getMimeType($export->file_type),
        ];
    }

    /**
     * Delete an export.
     */
    public function deleteExport(int $id): void
    {
        $export = DB::table('exports')->where('id', $id)->first();

        if ($export->file_path && Storage::disk('exports')->exists($export->file_path)) {
            Storage::disk('exports')->delete($export->file_path);
        }

        $export->delete();
    }

    /**
     * Clean up expired exports.
     */
    public function cleanupExpiredExports(): int
    {
        $expired = Export::expired()->get();
        $count = 0;

        foreach ($expired as $export) {
            $export->markAsExpired();
            $count++;
        }

        return $count;
    }

    // ==========================================
    // EXPORT TEMPLATE USE CASES
    // ==========================================

    /**
     * List export templates.
     */
    public function listExportTemplates(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DB::table('export_templates');

        if (!empty($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        // Filter by accessibility (user can access own templates or shared ones)
        if (!empty($filters['user_id'])) {
            $userId = $filters['user_id'];
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere('is_shared', true);
            });
        }

        if (!empty($filters['shared_only'])) {
            $query->where('is_shared', true);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Get a single export template.
     */
    public function getExportTemplate(int $id): ?ExportTemplate
    {
        return ExportTemplate::with(['module', 'user'])->find($id);
    }

    /**
     * Create an export template.
     */
    public function createExportTemplate(array $data): ExportTemplate
    {
        return DB::table('export_templates')->insertGetId([
            'module_id' => $data['module_id'],
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'selected_fields' => $data['selected_fields'],
            'filters' => $data['filters'] ?? [],
            'sorting' => $data['sorting'] ?? [],
            'export_options' => $data['export_options'] ?? [],
            'default_file_type' => $data['default_file_type'] ?? Export::FILE_TYPE_CSV,
            'is_shared' => $data['is_shared'] ?? false,
        ]);
    }

    /**
     * Update an export template.
     */
    public function updateExportTemplate(int $id, array $data): ExportTemplate
    {
        $template = DB::table('export_templates')->where('id', $id)->first();

        $template->update([
            'name' => $data['name'] ?? $template->name,
            'description' => $data['description'] ?? $template->description,
            'selected_fields' => $data['selected_fields'] ?? $template->selected_fields,
            'filters' => $data['filters'] ?? $template->filters,
            'sorting' => $data['sorting'] ?? $template->sorting,
            'export_options' => $data['export_options'] ?? $template->export_options,
            'default_file_type' => $data['default_file_type'] ?? $template->default_file_type,
            'is_shared' => $data['is_shared'] ?? $template->is_shared,
        ]);

        return $template->fresh();
    }

    /**
     * Delete an export template.
     */
    public function deleteExportTemplate(int $id): void
    {
        DB::table('export_templates')->where('id', $id)->first()->delete();
    }

    /**
     * Create export from template.
     */
    public function createExportFromTemplate(int $templateId, ?string $name = null, ?string $fileType = null): Export
    {
        $template = DB::table('export_templates')->where('id', $templateId)->first();
        $export = $template->createExport(Auth::id(), $name, $fileType);

        $totalRecords = $this->countExportRecords($export);
        $export->update(['total_records' => $totalRecords]);

        return $export;
    }

    // ==========================================
    // ANALYTICS USE CASES
    // ==========================================

    /**
     * Get import/export activity summary.
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

        $imports = $this->importRepository->getActivitySummary($userId, $period);

        // Keep Export queries as-is for now since we're only refactoring Import
        $exportQuery = DB::table('exports')->where('created_at', '>=', $dateFrom);
        if ($userId) {
            $exportQuery->where('user_id', $userId);
        }

        return [
            'imports' => $imports,
            'exports' => [
                'total' => $exportQuery->count(),
                'completed' => (clone $exportQuery)->where('status', Export::STATUS_COMPLETED)->count(),
                'failed' => (clone $exportQuery)->where('status', Export::STATUS_FAILED)->count(),
                'total_records' => (clone $exportQuery)->sum('exported_records'),
                'total_downloads' => (clone $exportQuery)->sum('download_count'),
            ],
            'period' => $period,
            'date_from' => $dateFrom->toIso8601String(),
        ];
    }

    /**
     * Get import error analysis.
     */
    public function getImportErrorAnalysis(int $importId): array
    {
        return $this->importRepository->getImportErrorAnalysis($importId);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Map row data using column mapping.
     */
    private function mapRowData(array $rowData, array $columnMapping): array
    {
        $mappedData = [];

        foreach ($columnMapping as $sourceColumn => $targetField) {
            if (isset($rowData[$sourceColumn])) {
                $mappedData[$targetField] = $rowData[$sourceColumn];
            }
        }

        return $mappedData;
    }

    /**
     * Validate row data against module fields.
     */
    private function validateRowData(array $data, int $moduleId): array
    {
        $errors = [];
        $module = Module::with('fields')->find($moduleId);

        if (!$module) {
            return ['module' => 'Module not found'];
        }

        foreach ($module->fields as $field) {
            if ($field->is_required && empty($data[$field->api_name])) {
                $errors[$field->api_name] = "{$field->label} is required";
            }
        }

        return $errors;
    }

    /**
     * Process a single import row.
     */
    private function processImportRow(array $import, array $row): ?int
    {
        $data = $row['mapped_data'];
        $moduleId = $import['module']['id'] ?? $import['module_id'];

        // Check for duplicates if configured
        $importOptions = $import['import_options'] ?? [];
        $duplicateCheckField = $importOptions['duplicate_check_field'] ?? null;

        if ($duplicateCheckField) {
            $existing = DB::table('module_records')->where('module_id', $moduleId)
                ->whereRaw("data->>'{$duplicateCheckField}' = ?", [$data[$duplicateCheckField] ?? null])
                ->first();

            if ($existing) {
                $handling = $importOptions['duplicate_handling'] ?? Import::DUPLICATE_SKIP;

                if ($handling === Import::DUPLICATE_SKIP) {
                    $rowModel = ImportRow::find($row['id']);
                    $rowModel?->markAsSkipped('Duplicate record found');
                    return null;
                }

                if ($handling === Import::DUPLICATE_UPDATE) {
                    $existing->update(['data' => array_merge($existing->data, $data)]);
                    return $existing->id;
                }
            }
        }

        // Create new record
        $record = DB::table('module_records')->insertGetId([
            'module_id' => $moduleId,
            'data' => $data,
            'created_by' => $import['user_id'],
        ]);

        return $record->id;
    }

    /**
     * Count records for export.
     */
    private function countExportRecords(Export $export): int
    {
        $query = DB::table('module_records')->where('module_id', $export->module_id);
        $this->applyExportFilters($query, $export->filters);
        return $query->count();
    }

    /**
     * Get records for export.
     */
    private function getExportRecords(Export $export): Collection
    {
        $query = DB::table('module_records')->where('module_id', $export->module_id);
        $this->applyExportFilters($query, $export->filters);
        $this->applyExportSorting($query, $export->sorting);
        return $query->get();
    }

    /**
     * Apply filters to export query.
     * Uses validated field names and operators to prevent SQL injection.
     */
    private function applyExportFilters($query, ?array $filters): void
    {
        if (empty($filters)) {
            return;
        }

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? '=';
            $value = $filter['value'] ?? null;

            if ($field && $value !== null) {
                try {
                    $column = $this->getSafeColumnExpression($field);
                    $safeOperator = $this->validateOperator($operator);
                    $query->whereRaw("{$column} {$safeOperator} ?", [$value]);
                } catch (\InvalidArgumentException $e) {
                    // Skip invalid fields
                    continue;
                }
            }
        }
    }

    /**
     * Apply sorting to export query.
     * Uses validated field names and directions to prevent SQL injection.
     */
    private function applyExportSorting($query, ?array $sorting): void
    {
        if (empty($sorting)) {
            $query->orderBy('id', 'asc');
            return;
        }

        foreach ($sorting as $sort) {
            $field = $sort['field'] ?? null;
            $direction = $sort['direction'] ?? 'asc';

            if ($field) {
                try {
                    $column = $this->getSafeColumnExpression($field);
                    $safeDirection = $this->validateDirection($direction);
                    $query->orderByRaw("{$column} {$safeDirection}");
                } catch (\InvalidArgumentException $e) {
                    // Skip invalid fields
                    continue;
                }
            }
        }
    }

    /**
     * Generate export file name.
     */
    private function generateExportFileName(Module $module, string $fileType): string
    {
        $timestamp = now()->format('Y-m-d_His');
        return "{$module->api_name}_export_{$timestamp}.{$fileType}";
    }

    /**
     * Generate export file.
     */
    private function generateExportFile(Export $export, Collection $records, string $fileName): string
    {
        $filePath = "exports/{$fileName}";

        if ($export->file_type === Export::FILE_TYPE_CSV) {
            $this->generateCsvFile($export, $records, $filePath);
        }
        // Add XLSX, PDF generation if needed

        return $filePath;
    }

    /**
     * Generate CSV export file.
     */
    private function generateCsvFile(Export $export, Collection $records, string $filePath): void
    {
        $fields = $export->selected_fields;
        $handle = fopen(Storage::disk('exports')->path($filePath), 'w');

        // Write headers
        fputcsv($handle, $fields);

        // Write data rows
        foreach ($records as $record) {
            $row = [];
            foreach ($fields as $field) {
                $row[] = $record->data[$field] ?? '';
            }
            fputcsv($handle, $row);

            $export->increment('exported_records');
        }

        fclose($handle);
    }

    /**
     * Get MIME type for file type.
     */
    private function getMimeType(string $fileType): string
    {
        return match ($fileType) {
            Export::FILE_TYPE_CSV => 'text/csv',
            Export::FILE_TYPE_XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            Export::FILE_TYPE_PDF => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    /**
     * Format bytes to human readable.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
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
