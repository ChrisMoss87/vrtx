<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Field;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Module;
use App\Models\ModuleRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ImportEngine
{
    public function __construct(
        protected FileParser $fileParser
    ) {}

    /**
     * Validate import data and create import rows.
     */
    public function validate(Import $import): void
    {
        $import->update(['status' => Import::STATUS_VALIDATING]);

        try {
            $module = $import->module;
            $fields = $module->fields()->get()->keyBy('api_name');
            $columnMapping = $import->column_mapping ?? [];
            $validationErrors = [];
            $rowNumber = 0;

            foreach ($this->fileParser->getAllRows($import->file_path, $import->file_type) as $excelRow => $data) {
                $rowNumber++;
                $mappedData = $this->mapRow($data, $columnMapping);
                $rowErrors = $this->validateRow($mappedData, $fields, $import->import_options ?? []);

                $import->rows()->create([
                    'row_number' => $rowNumber,
                    'original_data' => $data,
                    'mapped_data' => $mappedData,
                    'status' => empty($rowErrors) ? ImportRow::STATUS_PENDING : ImportRow::STATUS_FAILED,
                    'errors' => $rowErrors ?: null,
                ]);

                if (!empty($rowErrors)) {
                    $validationErrors["row_{$rowNumber}"] = $rowErrors;
                }
            }

            $import->update([
                'status' => Import::STATUS_VALIDATED,
                'total_rows' => $rowNumber,
                'validation_errors' => $validationErrors ?: null,
            ]);
        } catch (\Exception $e) {
            Log::error('Import validation failed', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
            ]);

            $import->markAsFailed('Validation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute the import.
     */
    public function execute(Import $import): void
    {
        $import->markAsStarted();

        try {
            $module = $import->module;
            $fields = $module->fields()->get()->keyBy('api_name');
            $options = $import->import_options ?? [];
            $duplicateHandling = $options['duplicate_handling'] ?? Import::DUPLICATE_SKIP;
            $duplicateField = $options['duplicate_check_field'] ?? null;

            $pendingRows = $import->rows()
                ->where('status', ImportRow::STATUS_PENDING)
                ->orderBy('row_number')
                ->cursor();

            foreach ($pendingRows as $row) {
                try {
                    $this->processRow($row, $module, $fields, $duplicateHandling, $duplicateField, $import->user_id);
                } catch (\Exception $e) {
                    $row->markAsFailed(['error' => $e->getMessage()]);
                }

                $import->incrementProcessed($row->fresh()->status);
            }

            $import->markAsCompleted();
        } catch (\Exception $e) {
            Log::error('Import execution failed', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
            ]);

            $import->markAsFailed('Import failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Map raw row data to module fields.
     */
    protected function mapRow(array $data, array $columnMapping): array
    {
        $mapped = [];

        foreach ($columnMapping as $fileColumn => $fieldApiName) {
            if ($fieldApiName && isset($data[$fileColumn])) {
                $mapped[$fieldApiName] = $this->cleanValue($data[$fileColumn]);
            }
        }

        return $mapped;
    }

    /**
     * Clean and normalize a value.
     */
    protected function cleanValue($value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            // Handle common boolean representations
            if (strtolower($value) === 'true' || $value === '1') {
                return true;
            }
            if (strtolower($value) === 'false' || $value === '0') {
                return false;
            }
        }

        return $value;
    }

    /**
     * Validate a single row.
     */
    protected function validateRow(array $data, $fields, array $options): array
    {
        $errors = [];
        $rules = [];
        $skipEmpty = $options['skip_empty_rows'] ?? true;

        // Check for completely empty row
        if ($skipEmpty && array_filter($data) === []) {
            return ['skipped' => 'Empty row'];
        }

        // Build validation rules from field definitions
        foreach ($fields as $apiName => $field) {
            if (!isset($data[$apiName])) {
                if ($field->is_required && empty($field->default_value)) {
                    $errors[$apiName] = "Required field '{$field->label}' is missing";
                }
                continue;
            }

            $fieldRules = $this->getFieldValidationRules($field);
            if (!empty($fieldRules)) {
                $rules[$apiName] = $fieldRules;
            }
        }

        // Run Laravel validation
        if (!empty($rules)) {
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                foreach ($validator->errors()->toArray() as $field => $messages) {
                    $errors[$field] = implode(', ', $messages);
                }
            }
        }

        return $errors;
    }

    /**
     * Get validation rules for a field.
     */
    protected function getFieldValidationRules(Field $field): array
    {
        $rules = [];

        if ($field->is_required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        switch ($field->type) {
            case 'email':
                $rules[] = 'email';
                break;
            case 'url':
                $rules[] = 'url';
                break;
            case 'number':
            case 'currency':
            case 'percent':
                $rules[] = 'numeric';
                if ($field->validation_rules['min'] ?? null) {
                    $rules[] = 'min:' . $field->validation_rules['min'];
                }
                if ($field->validation_rules['max'] ?? null) {
                    $rules[] = 'max:' . $field->validation_rules['max'];
                }
                break;
            case 'integer':
                $rules[] = 'integer';
                break;
            case 'date':
                $rules[] = 'date';
                break;
            case 'datetime':
                $rules[] = 'date';
                break;
            case 'select':
            case 'radio':
                $options = $field->options()->pluck('value')->toArray();
                if (!empty($options)) {
                    $rules[] = 'in:' . implode(',', $options);
                }
                break;
            case 'text':
            case 'textarea':
                if ($field->validation_rules['max_length'] ?? null) {
                    $rules[] = 'max:' . $field->validation_rules['max_length'];
                }
                break;
        }

        return $rules;
    }

    /**
     * Process a single import row.
     */
    protected function processRow(
        ImportRow $row,
        Module $module,
        $fields,
        string $duplicateHandling,
        ?string $duplicateField,
        int $userId
    ): void {
        $mappedData = $row->mapped_data;

        // Apply default values for missing fields
        foreach ($fields as $apiName => $field) {
            if (!isset($mappedData[$apiName]) && $field->default_value !== null) {
                $mappedData[$apiName] = $field->default_value;
            }
        }

        // Transform values based on field type
        $mappedData = $this->transformValues($mappedData, $fields);

        // Check for duplicates
        if ($duplicateField && isset($mappedData[$duplicateField])) {
            $existingRecord = ModuleRecord::where('module_id', $module->id)
                ->whereJsonContains('data->' . $duplicateField, $mappedData[$duplicateField])
                ->first();

            if ($existingRecord) {
                switch ($duplicateHandling) {
                    case Import::DUPLICATE_SKIP:
                        $row->markAsSkipped('Duplicate record found');
                        return;

                    case Import::DUPLICATE_UPDATE:
                        $existingRecord->update([
                            'data' => array_merge($existingRecord->data, $mappedData),
                            'updated_by' => $userId,
                        ]);
                        $row->markAsSuccess($existingRecord->id);
                        return;

                    case Import::DUPLICATE_CREATE:
                        // Fall through to create new record
                        break;
                }
            }
        }

        // Create new record
        DB::transaction(function () use ($row, $module, $mappedData, $userId) {
            $record = ModuleRecord::create([
                'module_id' => $module->id,
                'data' => $mappedData,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $row->markAsSuccess($record->id);
        });
    }

    /**
     * Transform values based on field types.
     */
    protected function transformValues(array $data, $fields): array
    {
        foreach ($data as $apiName => $value) {
            if (!isset($fields[$apiName]) || $value === null) {
                continue;
            }

            $field = $fields[$apiName];
            $data[$apiName] = $this->transformValue($value, $field);
        }

        return $data;
    }

    /**
     * Transform a single value based on field type.
     */
    protected function transformValue($value, Field $field): mixed
    {
        switch ($field->type) {
            case 'number':
            case 'currency':
            case 'percent':
                // Remove currency symbols and format
                if (is_string($value)) {
                    $value = preg_replace('/[^\d.\-]/', '', $value);
                }
                return is_numeric($value) ? (float) $value : null;

            case 'integer':
                return is_numeric($value) ? (int) $value : null;

            case 'boolean':
            case 'switch':
                if (is_bool($value)) {
                    return $value;
                }
                return in_array(strtolower((string) $value), ['true', 'yes', '1', 'on']);

            case 'date':
                return $this->parseDate($value);

            case 'datetime':
                return $this->parseDateTime($value);

            case 'multiselect':
                // Handle comma-separated values
                if (is_string($value)) {
                    return array_map('trim', explode(',', $value));
                }
                return is_array($value) ? $value : [$value];

            case 'json':
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
                }
                return $value;

            default:
                return $value;
        }
    }

    /**
     * Parse date value.
     */
    protected function parseDate($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            $date = new \DateTime($value);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse datetime value.
     */
    protected function parseDateTime($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            $date = new \DateTime($value);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Auto-detect column mapping based on header names.
     */
    public function autoMapColumns(array $headers, Module $module): array
    {
        $fields = $module->fields()->get();
        $mapping = [];

        foreach ($headers as $header) {
            $normalizedHeader = $this->normalizeString($header);
            $bestMatch = null;
            $bestScore = 0;

            foreach ($fields as $field) {
                // Check exact matches
                if ($normalizedHeader === $this->normalizeString($field->api_name)) {
                    $bestMatch = $field->api_name;
                    break;
                }

                if ($normalizedHeader === $this->normalizeString($field->label)) {
                    $bestMatch = $field->api_name;
                    break;
                }

                // Calculate similarity score
                $score = max(
                    similar_text($normalizedHeader, $this->normalizeString($field->api_name)),
                    similar_text($normalizedHeader, $this->normalizeString($field->label))
                );

                if ($score > $bestScore && $score > strlen($normalizedHeader) * 0.6) {
                    $bestScore = $score;
                    $bestMatch = $field->api_name;
                }
            }

            $mapping[$header] = $bestMatch;
        }

        return $mapping;
    }

    /**
     * Normalize string for comparison.
     */
    protected function normalizeString(string $str): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $str));
    }
}
