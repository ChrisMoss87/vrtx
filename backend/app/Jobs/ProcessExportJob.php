<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Illuminate\Support\Facades\DB;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600; // 1 hour

    public function __construct(
        public Export $export
    ) {}

    public function handle(): void
    {
        Log::info('Starting export', ['export_id' => $this->export->id]);

        $this->export->markAsStarted();

        try {
            $module = $this->export->module;
            $fields = $module->fields()
                ->whereIn('api_name', $this->export->selected_fields)
                ->orderByRaw('FIELD(api_name, "' . implode('","', $this->export->selected_fields) . '")')
                ->get();

            $options = $this->export->export_options ?? [];
            $includeHeaders = $options['include_headers'] ?? true;
            $dateFormat = $options['date_format'] ?? 'Y-m-d';

            // Create spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(Str::limit($module->name, 31));

            $row = 1;

            // Add headers
            if ($includeHeaders) {
                $col = 1;
                foreach ($fields as $field) {
                    $sheet->setCellValueByColumnAndRow($col, $row, $field->label);
                    $col++;
                }
                $row++;
            }

            // Query records
            $query = $module->records();

            // Apply filters
            if (!empty($this->export->filters)) {
                $query = $this->applyFilters($query, $this->export->filters);
            }

            // Apply sorting
            if (!empty($this->export->sorting)) {
                foreach ($this->export->sorting as $sort) {
                    $query->orderBy(
                        "data->{$sort['field']}",
                        $sort['direction'] ?? 'asc'
                    );
                }
            }

            // Process records in chunks
            $exportedCount = 0;
            $query->chunk(1000, function ($records) use (&$sheet, &$row, $fields, $dateFormat, &$exportedCount) {
                foreach ($records as $record) {
                    $col = 1;
                    foreach ($fields as $field) {
                        $value = $record->data[$field->api_name] ?? null;
                        $value = $this->formatValue($value, $field, $dateFormat);
                        $sheet->setCellValueByColumnAndRow($col, $row, $value);
                        $col++;
                    }
                    $row++;
                    $exportedCount++;
                }
            });

            // Generate file
            $fileName = Str::slug($this->export->name) . '-' . now()->format('Y-m-d-His') . '.' . $this->export->file_type;
            $filePath = 'exports/' . date('Y/m') . '/' . Str::uuid() . '.' . $this->export->file_type;

            // Ensure directory exists
            Storage::disk('exports')->makeDirectory(dirname($filePath));

            $tempPath = Storage::disk('exports')->path($filePath);

            // Write file
            $writer = match ($this->export->file_type) {
                'csv' => new CsvWriter($spreadsheet),
                'xlsx' => new XlsxWriter($spreadsheet),
                default => throw new \InvalidArgumentException("Unsupported file type: {$this->export->file_type}"),
            };

            $writer->save($tempPath);

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

            match ($operator) {
                '=' => $query->where("data->{$field}", $value),
                '!=' => $query->where("data->{$field}", '!=', $value),
                '>' => $query->where("data->{$field}", '>', $value),
                '>=' => $query->where("data->{$field}", '>=', $value),
                '<' => $query->where("data->{$field}", '<', $value),
                '<=' => $query->where("data->{$field}", '<=', $value),
                'contains' => $query->where("data->{$field}", 'like', "%{$value}%"),
                'starts_with' => $query->where("data->{$field}", 'like', "{$value}%"),
                'ends_with' => $query->where("data->{$field}", 'like', "%{$value}"),
                'is_null' => $query->whereNull("data->{$field}"),
                'is_not_null' => $query->whereNotNull("data->{$field}"),
                'in' => $query->whereIn("data->{$field}", (array) $value),
                'not_in' => $query->whereNotIn("data->{$field}", (array) $value),
                default => $query->where("data->{$field}", $value),
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
