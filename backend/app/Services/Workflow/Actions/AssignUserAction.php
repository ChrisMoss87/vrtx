<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use App\Models\ModuleRecord;

class AssignUserAction implements ActionInterface
{
    public function execute(array $config, array $context): array
    {
        $recordId = $context['record']['id'] ?? null;
        $userId = $config['user_id'] ?? null;
        $field = $config['field'] ?? 'owner_id';

        if (!$recordId) {
            throw new \InvalidArgumentException('Record ID is required');
        }

        $record = ModuleRecord::find($recordId);
        if (!$record) {
            throw new \InvalidArgumentException("Record not found: {$recordId}");
        }

        $data = $record->data;
        $data[$field] = $userId;
        $record->data = $data;
        $record->save();

        return ['assigned' => true, 'user_id' => $userId, 'field' => $field];
    }

    public static function getConfigSchema(): array
    {
        return ['fields' => [
            ['name' => 'user_id', 'label' => 'Assign To', 'type' => 'user_select', 'required' => true],
            ['name' => 'field', 'label' => 'Assignment Field', 'type' => 'text', 'default' => 'owner_id'],
        ]];
    }

    public function validate(array $config): array
    {
        return empty($config['user_id']) ? ['user_id' => 'User is required'] : [];
    }
}
