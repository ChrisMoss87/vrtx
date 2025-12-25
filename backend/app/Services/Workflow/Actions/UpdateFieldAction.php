<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;


/**
 * Action to update a field on the current record.
 */
class UpdateFieldAction implements ActionInterface
{
    /**
     * Execute the update field action.
     */
    public function execute(array $config, array $context): array
    {
        $recordId = $context['record']['id'] ?? null;

        if (!$recordId) {
            throw new \InvalidArgumentException('No record ID in context');
        }

        $record = DB::table('module_records')->where('id', $recordId)->first();

        if (!$record) {
            throw new \InvalidArgumentException("Record not found: {$recordId}");
        }

        $updates = $config['updates'] ?? [];
        $data = $record->data;
        $updatedFields = [];

        foreach ($updates as $update) {
            $field = $update['field'] ?? null;
            $value = $update['value'] ?? null;
            $valueType = $update['value_type'] ?? 'static';

            if (!$field) {
                continue;
            }

            // Resolve value
            $resolvedValue = match ($valueType) {
                'static' => $value,
                'field' => $context['record']['data'][$value] ?? null,
                'formula' => $this->evaluateFormula($value, $context),
                'now' => now()->toISOString(),
                'null' => null,
                default => $value,
            };

            $data[$field] = $resolvedValue;
            $updatedFields[$field] = $resolvedValue;
        }

        $record->data = $data;
        $record->save();

        return [
            'updated' => true,
            'record_id' => $recordId,
            'fields_updated' => $updatedFields,
        ];
    }

    /**
     * Get the configuration schema.
     */
    public static function getConfigSchema(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'updates',
                    'label' => 'Field Updates',
                    'type' => 'field_updates',
                    'required' => true,
                    'description' => 'Fields to update and their new values',
                    'schema' => [
                        [
                            'name' => 'field',
                            'label' => 'Field',
                            'type' => 'field_select',
                            'required' => true,
                        ],
                        [
                            'name' => 'value_type',
                            'label' => 'Value Type',
                            'type' => 'select',
                            'options' => [
                                ['value' => 'static', 'label' => 'Static Value'],
                                ['value' => 'field', 'label' => 'Copy from Field'],
                                ['value' => 'formula', 'label' => 'Formula'],
                                ['value' => 'now', 'label' => 'Current Date/Time'],
                                ['value' => 'null', 'label' => 'Clear (Null)'],
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'value',
                            'label' => 'Value',
                            'type' => 'dynamic',
                            'depends_on' => 'value_type',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Validate the configuration.
     */
    public function validate(array $config): array
    {
        $errors = [];

        if (empty($config['updates'])) {
            $errors['updates'] = 'At least one field update is required';
        }

        foreach ($config['updates'] ?? [] as $index => $update) {
            if (empty($update['field'])) {
                $errors["updates.{$index}.field"] = 'Field is required';
            }
        }

        return $errors;
    }

    /**
     * Evaluate a simple formula safely without using eval().
     */
    protected function evaluateFormula(string $formula, array $context): mixed
    {
        // Use safe expression evaluator
        return \App\Services\Workflow\SafeExpressionEvaluator::evaluate($formula, [
            'record' => $context['record'] ?? [],
        ]);
    }
}
