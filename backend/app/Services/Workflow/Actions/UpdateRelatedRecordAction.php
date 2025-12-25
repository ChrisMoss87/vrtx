<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Action to update related records (parent/child relationships).
 */
class UpdateRelatedRecordAction implements ActionInterface
{
    public const RELATION_PARENT = 'parent';
    public const RELATION_CHILD = 'child';
    public const RELATION_LINKED = 'linked';

    public function execute(array $config, array $context): array
    {
        $recordId = $context['record']['id'] ?? null;
        $relationType = $config['relation_type'] ?? self::RELATION_LINKED;
        $relatedModuleApi = $config['related_module'] ?? null;
        $relationField = $config['relation_field'] ?? null;
        $fieldUpdates = $config['field_updates'] ?? [];
        $updateAll = $config['update_all'] ?? false;

        if (!$recordId) {
            throw new \InvalidArgumentException('Record ID is required');
        }

        $record = DB::table('module_records')->where('id', $recordId)->first();
        if (!$record) {
            throw new \InvalidArgumentException("Record not found: {$recordId}");
        }

        // Find related records
        $relatedRecords = $this->findRelatedRecords(
            $record,
            $relationType,
            $relatedModuleApi,
            $relationField,
            $context
        );

        if ($relatedRecords->isEmpty()) {
            return [
                'updated' => false,
                'message' => 'No related records found',
                'records_updated' => 0,
            ];
        }

        // Apply limit if not updating all
        if (!$updateAll && $relatedRecords->count() > 1) {
            $relatedRecords = $relatedRecords->take(1);
        }

        $updatedCount = 0;
        $updatedIds = [];

        foreach ($relatedRecords as $relatedRecord) {
            $data = $relatedRecord->data;

            foreach ($fieldUpdates as $fieldUpdate) {
                $fieldName = $fieldUpdate['field'] ?? null;
                $value = $this->resolveValue($fieldUpdate, $context, $record);

                if ($fieldName) {
                    $data[$fieldName] = $value;
                }
            }

            $relatedRecord->data = $data;
            $relatedRecord->updated_by = $context['triggered_by'] ?? null;
            $relatedRecord->save();

            $updatedCount++;
            $updatedIds[] = $relatedRecord->id;
        }

        Log::info('Workflow updated related records', [
            'source_record_id' => $recordId,
            'relation_type' => $relationType,
            'records_updated' => $updatedCount,
            'updated_ids' => $updatedIds,
        ]);

        return [
            'updated' => true,
            'records_updated' => $updatedCount,
            'updated_record_ids' => $updatedIds,
            'relation_type' => $relationType,
        ];
    }

    /**
     * Find related records based on relation type.
     */
    protected function findRelatedRecords(
        ModuleRecord $record,
        string $relationType,
        ?string $relatedModuleApi,
        ?string $relationField,
        array $context
    ) {
        $recordData = $record->data;

        switch ($relationType) {
            case self::RELATION_PARENT:
                // Find parent record via lookup field on current record
                if (!$relationField) {
                    return collect();
                }

                $parentId = $recordData[$relationField] ?? null;
                if (!$parentId) {
                    return collect();
                }

                return DB::table('module_records')->where('id', $parentId)->get();

            case self::RELATION_CHILD:
                // Find child records that reference current record
                if (!$relatedModuleApi || !$relationField) {
                    return collect();
                }

                $relatedModule = DB::table('modules')->where('api_name', $relatedModuleApi)->first();
                if (!$relatedModule) {
                    return collect();
                }

                return DB::table('module_records')->where('module_id', $relatedModule->id)
                    ->whereJsonContains("data->{$relationField}", $record->id)
                    ->get();

            case self::RELATION_LINKED:
            default:
                // Find records linked via a specific field
                if (!$relatedModuleApi || !$relationField) {
                    return collect();
                }

                $relatedModule = DB::table('modules')->where('api_name', $relatedModuleApi)->first();
                if (!$relatedModule) {
                    return collect();
                }

                // Get the value from the current record's field
                $linkedValue = $recordData[$relationField] ?? null;
                if (!$linkedValue) {
                    return collect();
                }

                // Find records in related module where the relation field matches
                return DB::table('module_records')->where('module_id', $relatedModule->id)
                    ->where('id', $linkedValue)
                    ->get();
        }
    }

    /**
     * Resolve the value for a field update.
     */
    protected function resolveValue(array $fieldUpdate, array $context, ModuleRecord $sourceRecord): mixed
    {
        $valueType = $fieldUpdate['value_type'] ?? 'static';
        $value = $fieldUpdate['value'] ?? null;

        return match ($valueType) {
            'static' => $value,
            'field' => $this->getFieldValue($value, $context, $sourceRecord),
            'formula' => $this->evaluateFormula($value, $context, $sourceRecord),
            'current_date' => now()->format('Y-m-d'),
            'current_datetime' => now()->format('Y-m-d H:i:s'),
            'current_user' => $context['triggered_by'] ?? null,
            default => $value,
        };
    }

    /**
     * Get value from a field path.
     */
    protected function getFieldValue(string $fieldPath, array $context, ModuleRecord $sourceRecord): mixed
    {
        // Check if path starts with 'record.' or 'source.'
        if (str_starts_with($fieldPath, 'record.') || str_starts_with($fieldPath, 'source.')) {
            $field = substr($fieldPath, strpos($fieldPath, '.') + 1);
            return $sourceRecord->data[$field] ?? null;
        }

        // Check context
        $keys = explode('.', $fieldPath);
        $value = $context;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Evaluate a simple formula safely without using eval().
     */
    protected function evaluateFormula(string $formula, array $context, ModuleRecord $sourceRecord): mixed
    {
        // Build context with source record data
        $evalContext = [
            'record' => $context['record'] ?? [],
            'source' => $sourceRecord->data ?? [],
        ];

        // Use safe expression evaluator
        return \App\Services\Workflow\SafeExpressionEvaluator::evaluate($formula, $evalContext);
    }

    public static function getConfigSchema(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'relation_type',
                    'label' => 'Relation Type',
                    'type' => 'select',
                    'required' => true,
                    'default' => self::RELATION_LINKED,
                    'options' => [
                        ['value' => self::RELATION_PARENT, 'label' => 'Parent Record (via lookup)'],
                        ['value' => self::RELATION_CHILD, 'label' => 'Child Records'],
                        ['value' => self::RELATION_LINKED, 'label' => 'Linked Record'],
                    ],
                ],
                [
                    'name' => 'related_module',
                    'label' => 'Related Module',
                    'type' => 'module_select',
                    'required' => false,
                    'description' => 'Select the module of related records',
                    'show_when' => ['relation_type' => [self::RELATION_CHILD, self::RELATION_LINKED]],
                ],
                [
                    'name' => 'relation_field',
                    'label' => 'Relation Field',
                    'type' => 'field_select',
                    'required' => true,
                    'description' => 'Field that links the records',
                ],
                [
                    'name' => 'field_updates',
                    'label' => 'Field Updates',
                    'type' => 'field_mapping_list',
                    'required' => true,
                    'description' => 'Fields to update on related records',
                    'item_schema' => [
                        'field' => [
                            'type' => 'field_select',
                            'label' => 'Field to Update',
                            'required' => true,
                        ],
                        'value_type' => [
                            'type' => 'select',
                            'label' => 'Value Type',
                            'options' => [
                                ['value' => 'static', 'label' => 'Static Value'],
                                ['value' => 'field', 'label' => 'From Source Field'],
                                ['value' => 'formula', 'label' => 'Formula'],
                                ['value' => 'current_date', 'label' => 'Current Date'],
                                ['value' => 'current_datetime', 'label' => 'Current Date/Time'],
                                ['value' => 'current_user', 'label' => 'Current User'],
                            ],
                        ],
                        'value' => [
                            'type' => 'dynamic',
                            'label' => 'Value',
                            'supports_variables' => true,
                        ],
                    ],
                ],
                [
                    'name' => 'update_all',
                    'label' => 'Update All Matching Records',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => false,
                    'description' => 'If unchecked, only updates the first matching record',
                ],
            ],
        ];
    }

    public function validate(array $config): array
    {
        $errors = [];

        if (empty($config['relation_field'])) {
            $errors['relation_field'] = 'Relation field is required';
        }

        $fieldUpdates = $config['field_updates'] ?? [];
        if (empty($fieldUpdates)) {
            $errors['field_updates'] = 'At least one field update is required';
        }

        foreach ($fieldUpdates as $index => $update) {
            if (empty($update['field'])) {
                $errors["field_updates.{$index}.field"] = 'Field is required';
            }
        }

        $relationType = $config['relation_type'] ?? self::RELATION_LINKED;
        if (in_array($relationType, [self::RELATION_CHILD, self::RELATION_LINKED]) && empty($config['related_module'])) {
            $errors['related_module'] = 'Related module is required for this relation type';
        }

        return $errors;
    }
}
