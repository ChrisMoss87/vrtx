<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Infrastructure\Services\Spreadsheet\CsvService;
use App\Infrastructure\Services\Spreadsheet\XlsxWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600; // 1 hour

    /**
     * System fields that are stored directly on the table.
     */
    private const SYSTEM_FIELDS = ['id', 'module_id', 'created_at', 'updated_at', 'owner_id', 'created_by'];

    public function __construct(
        public Export $export
    ) {}

    /**
     * Validate field name to prevent SQL injection.
     * Only allows alphanumeric characters and underscores.
     */
    private function validateFieldName(string $field): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
            throw new \InvalidArgumentException("Invalid field name: {$field}");
        }

        return $field;
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

    public function handle(CsvService $csvService, XlsxWriter $xlsxWriter): void
    {
        Log::info('Starting export', ['export_id' => $this->export->id]);

        $this->export->markAsStarted();

        try {
            $module = $this->export->module;
            // Validate selected fields before using in query
            $validatedFields = [];
            foreach ($this->export->selected_fields as $fieldName) {
                try {
                    $validatedFields[] = $this->validateFieldName($fieldName);
                } catch (\InvalidArgumentException $e) {
                    // Skip invalid field names
                    continue;
                }
            }

            $fields = $module->fields()
                ->whereIn('api_name', $validatedFields)
                ->get()
                ->sortBy(function ($field) use ($validatedFields) {
                    return array_search($field->api_name, $validatedFields);
                })
                ->values();

            $options = $this->export->export_options ?? [];
            $includeHeaders = $options['include_headers'] ?? true;
            $dateFormat = $options['date_format'] ?? 'Y-m-d';

            // Build headers
            $headers = [];
            if ($includeHeaders) {
                foreach ($fields as $field) {
                    $headers[] = $field->label;
                }
            }

            // Query records
            $query = $module->records();

            // Apply filters
            if (!empty($this->export->filters)) {
                $query = $this->applyFilters($query, $this->export->filters);
            }

            // Apply sorting with validation
            if (!empty($this->export->sorting)) {
                foreach ($this->export->sorting as $sort) {
                    $field = $sort['field'] ?? null;
                    $direction = $sort['direction'] ?? 'asc';

                    if ($field) {
                        try {
                            $column = $this->getSafeColumnExpression($field);
                            $safeDirection = $this->validateDirection($direction);
                            $query->orderByRaw("{$column} {$safeDirection}");
                        } catch (\InvalidArgumentException $e) {
                            // Skip invalid field names
                            continue;
                        }
                    }
                }
            }

            // Generate file path
            $fileName = Str::slug($this->export->name) . '-' . now()->format('Y-m-d-His') . '.' . $this->export->file_type;
            $filePath = 'exports/' . date('Y/m') . '/' . Str::uuid() . '.' . $this->export->file_type;

            // Ensure directory exists
            Storage::disk('exports')->makeDirectory(dirname($filePath));

            $tempPath = Storage::disk('exports')->path($filePath);

            // Export based on file type
            $exportedCount = match ($this->export->file_type) {
                'csv' => $this->exportToCsv($csvService, $tempPath, $query, $fields, $headers, $dateFormat),
                'xlsx' => $this->exportToXlsx($xlsxWriter, $tempPath, $query, $fields, $headers, $dateFormat, $module->name),
                default => throw new \InvalidArgumentException("Unsupported file type: {$this->export->file_type}"),
            };

            $fileSize = filesize($tempPath);

            $this->export->markAsCompleted($filePath, $fileName, $fileSize, $exportedCount);

            Log::info('Export completed', [
                'export_id' => $this->export->id,
                'records' => $exportedCount,
                'file_size' => $fileSize,
            ]);
        } catch (\Exception $e) {
            Log::error('Export failed', [
                'export_id' => $this->export->id,
                'error' => $e->getMessage(),
            ]);

            $this->export->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Export to CSV using native service.
     */
    protected function exportToCsv(
        CsvService $csvService,
        string $filePath,
        $query,
        $fields,
        array $headers,
        string $dateFormat
    ): int {
        $rows = [];
        $exportedCount = 0;

        $query->chunk(1000, function ($records) use (&$rows, $fields, $dateFormat, &$exportedCount) {
            foreach ($records as $record) {
                $row = [];
                foreach ($fields as $field) {
                    $value = $record->data[$field->api_name] ?? null;
                    $row[] = $this->formatValue($value, $field, $dateFormat);
                }
                $rows[] = $row;
                $exportedCount++;
            }
        });

        $csvService->write($filePath, $rows, [
            'headers' => $headers,
            'include_headers' => !empty($headers),
        ]);

        return $exportedCount;
    }

    /**
     * Export to Excel using native service.
     */
    protected function exportToXlsx(
        XlsxWriter $xlsxWriter,
        string $filePath,
        $query,
        $fields,
        array $headers,
        string $dateFormat,
        string $sheetName
    ): int {
        $rows = [];
        $exportedCount = 0;

        $query->chunk(1000, function ($records) use (&$rows, $fields, $dateFormat, &$exportedCount) {
            foreach ($records as $record) {
                $row = [];
                foreach ($fields as $field) {
                    $value = $record->data[$field->api_name] ?? null;
                    $row[] = $this->formatValue($value, $field, $dateFormat);
                }
                $rows[] = $row;
                $exportedCount++;
            }
        });

        $xlsxWriter->reset();
        $xlsxWriter->addSheet(
            mb_substr($sheetName, 0, 31),
            $rows,
            ['headers' => $headers, 'header_style' => true]
        );
        $xlsxWriter->save($filePath);

        return $exportedCount;
    }

    /**
     * Format value for export.
     */
    protected function formatValue($value, $field, string $dateFormat): mixed
    {
        if ($value === null) {
            return '';
        }

        return match ($field->type) {
            'date' => $this->formatDate($value, $dateFormat),
            'datetime' => $this->formatDate($value, $dateFormat . ' H:i:s'),
            'boolean', 'switch' => $value ? 'Yes' : 'No',
            'multiselect' => is_array($value) ? implode(', ', $value) : $value,
            'json' => is_array($value) ? json_encode($value) : $value,
            'currency' => is_numeric($value) ? number_format((float) $value, 2) : $value,
            'percent' => is_numeric($value) ? $value . '%' : $value,
            default => $value,
        };
    }

    /**
     * Format date value.
     */
    protected function formatDate($value, string $format): string
    {
        try {
            return (new \DateTime($value))->format($format);
        } catch (\Exception $e) {
            return (string) $value;
        }
    }

    /**
     * Apply filters to query.
     * Uses validated field names to prevent SQL injection.
     */
    protected function applyFilters($query, array $filters)
    {
        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? '=';
            $value = $filter['value'] ?? null;

            if (!$field) {
                continue;
            }

            try {
                $column = $this->getSafeColumnExpression($field);
            } catch (\InvalidArgumentException $e) {
                // Skip invalid field names
                continue;
            }

            match ($operator) {
                '=' => $query->whereRaw("{$column} = ?", [$value]),
                '!=' => $query->whereRaw("{$column} != ?", [$value]),
                '>' => $query->whereRaw("{$column} > ?", [$value]),
                '>=' => $query->whereRaw("{$column} >= ?", [$value]),
                '<' => $query->whereRaw("{$column} < ?", [$value]),
                '<=' => $query->whereRaw("{$column} <= ?", [$value]),
                'contains' => $query->whereRaw("{$column} ILIKE ?", ["%{$value}%"]),
                'starts_with' => $query->whereRaw("{$column} ILIKE ?", ["{$value}%"]),
                'ends_with' => $query->whereRaw("{$column} ILIKE ?", ["%{$value}"]),
                'is_null' => $query->whereRaw("{$column} IS NULL"),
                'is_not_null' => $query->whereRaw("{$column} IS NOT NULL"),
                'in' => $query->whereRaw("{$column} = ANY(?)", [(array) $value]),
                'not_in' => $query->whereRaw("{$column} != ALL(?)", [(array) $value]),
                default => $query->whereRaw("{$column} = ?", [$value]),
            };
        }

        return $query;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessExportJob failed', [
            'export_id' => $this->export->id,
            'error' => $exception->getMessage(),
        ]);

        $this->export->markAsFailed('Export job failed: ' . $exception->getMessage());
    }
}
