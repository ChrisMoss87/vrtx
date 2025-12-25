<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;


class DeleteRecordAction implements ActionInterface
{
    public function execute(array $config, array $context): array
    {
        $recordId = $config['record_id'] ?? $context['record']['id'] ?? null;

        if (!$recordId) {
            throw new \InvalidArgumentException('Record ID is required');
        }

        $record = DB::table('module_records')->where('id', $recordId)->first();
        if (!$record) {
            return ['deleted' => false, 'reason' => 'Record not found'];
        }

        $record->delete();

        return ['deleted' => true, 'record_id' => $recordId];
    }

    public static function getConfigSchema(): array
    {
        return ['fields' => [
            ['name' => 'record_id', 'label' => 'Record ID', 'type' => 'text', 'required' => false, 'description' => 'Leave empty to delete the triggering record'],
        ]];
    }

    public function validate(array $config): array
    {
        return [];
    }
}
