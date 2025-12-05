<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use App\Models\Module;
use App\Models\ModuleRecord;

/**
 * Action to create a new record.
 */
class CreateRecordAction implements ActionInterface
{
    public function execute(array $config, array $context): array
    {
        $moduleApiName = $config['module'] ?? null;

        if (!$moduleApiName) {
            throw new \InvalidArgumentException('Module is required');
        }

        $module = Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            throw new \InvalidArgumentException("Module not found: {$moduleApiName}");
        }

        // Build record data from field mappings
        $data = [];
        foreach ($config['field_mappings'] ?? [] as $mapping) {
            $field = $mapping['field'] ?? null;
            $value = $this->resolveValue($mapping, $context);

            if ($field) {
                $data[$field] = $value;
            }
        }

        $record = ModuleRecord::create([
            'module_id' => $module->id,
            'data' => $data,
            'created_by' => $context['user_id'] ?? null,
            'updated_by' => $context['user_id'] ?? null,
        ]);

        return [
            'created' => true,
            'record_id' => $record->id,
            'module' => $moduleApiName,
            'data' => $data,
        ];
    }

    public static function getConfigSchema(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'module',
                    'label' => 'Module',
                    'type' => 'module_select',
                    'required' => true,
                ],
                [
                    'name' => 'field_mappings',
                    'label' => 'Field Values',
                    'type' => 'field_mappings',
                    'required' => true,
                    'depends_on' => 'module',
                ],
            ],
        ];
    }

    public function validate(array $config): array
    {
        $errors = [];

        if (empty($config['module'])) {
            $errors['module'] = 'Module is required';
        }

        return $errors;
    }

    protected function resolveValue(array $mapping, array $context): mixed
    {
        $valueType = $mapping['value_type'] ?? 'static';
        $value = $mapping['value'] ?? null;

        return match ($valueType) {
            'static' => $value,
            'field' => $context['record']['data'][$value] ?? null,
            'record_id' => $context['record']['id'] ?? null,
            'now' => now()->toISOString(),
            default => $value,
        };
    }
}
