<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use App\Models\ModuleRecord;

class UpdateRecordAction implements ActionInterface
{
    public function execute(array $config, array $context): array
    {
        $recordId = $config['record_id'] ?? $context['record']['id'] ?? null;

        if (!$recordId) {
            throw new \InvalidArgumentException('Record ID is required');
        }

        $record = ModuleRecord::find($recordId);
        if (!$record) {
            throw new \InvalidArgumentException("Record not found: {$recordId}");
        }

        $data = $record->data;
        foreach ($config['updates'] ?? [] as $field => $value) {
            $data[$field] = $value;
        }

        $record->data = $data;
        $record->save();

        return ['updated' => true, 'record_id' => $recordId];
    }

    public static function getConfigSchema(): array
    {
        return ['fields' => [
            ['name' => 'record_id', 'label' => 'Record ID', 'type' => 'text', 'required' => false],
            ['name' => 'updates', 'label' => 'Field Updates', 'type' => 'field_updates', 'required' => true],
        ]];
    }

    public function validate(array $config): array
    {
        return empty($config['updates']) ? ['updates' => 'At least one update is required'] : [];
    }
}
