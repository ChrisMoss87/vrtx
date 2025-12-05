<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use Illuminate\Support\Facades\Log;

class SendNotificationAction implements ActionInterface
{
    public function execute(array $config, array $context): array
    {
        $userIds = $config['user_ids'] ?? [];
        $title = $config['title'] ?? 'Workflow Notification';
        $message = $config['message'] ?? '';

        // TODO: Implement actual notification system
        Log::info('Workflow notification', [
            'user_ids' => $userIds,
            'title' => $title,
            'message' => $message,
        ]);

        return ['notified' => true, 'user_count' => count($userIds)];
    }

    public static function getConfigSchema(): array
    {
        return ['fields' => [
            ['name' => 'user_ids', 'label' => 'Notify Users', 'type' => 'user_multi_select', 'required' => true],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
            ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true, 'supports_variables' => true],
        ]];
    }

    public function validate(array $config): array
    {
        $errors = [];
        if (empty($config['user_ids'])) $errors['user_ids'] = 'At least one user is required';
        if (empty($config['title'])) $errors['title'] = 'Title is required';
        return $errors;
    }
}
