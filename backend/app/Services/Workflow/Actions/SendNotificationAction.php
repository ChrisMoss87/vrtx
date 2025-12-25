<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SendNotificationAction implements ActionInterface
{
    public function __construct(
        protected ?NotificationService $notificationService = null
    ) {
        $this->notificationService = $notificationService ?? app(NotificationService::class);
    }

    public function execute(array $config, array $context): array
    {
        $userIds = $this->resolveUserIds($config, $context);
        $title = $this->replaceVariables($config['title'] ?? 'Workflow Notification', $context);
        $message = $this->replaceVariables($config['message'] ?? '', $context);
        $notificationType = $config['notification_type'] ?? 'info';
        $linkToRecord = $config['link_to_record'] ?? true;

        // Determine action URL based on context
        $actionUrl = null;
        $actionLabel = null;
        if ($linkToRecord && isset($context['record'], $context['module'])) {
            $actionUrl = "/{$context['module']['api_name']}/{$context['record']['id']}";
            $actionLabel = 'View Record';
        }

        // Send notifications to all resolved users
        $sentCount = 0;
        foreach ($userIds as $userId) {
            try {
                $this->notificationService->notify(
                    (int) $userId,
                    $this->mapNotificationType($notificationType),
                    $title,
                    $message,
                    $actionUrl,
                    $actionLabel,
                    null,
                    [
                        'workflow_id' => $context['workflow']['id'] ?? null,
                        'workflow_name' => $context['workflow']['name'] ?? null,
                        'record_id' => $context['record']['id'] ?? null,
                        'module' => $context['module']['api_name'] ?? null,
                        'notification_type' => $notificationType,
                    ]
                );
                $sentCount++;
            } catch (\Exception $e) {
                Log::warning('Failed to send workflow notification', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Workflow notification sent', [
            'user_ids' => $userIds,
            'title' => $title,
            'sent_count' => $sentCount,
        ]);

        return ['notified' => true, 'user_count' => $sentCount];
    }

    protected function resolveUserIds(array $config, array $context): array
    {
        $recipientType = $config['recipient_type'] ?? 'specific_users';
        $userIds = [];

        switch ($recipientType) {
            case 'record_owner':
                if (isset($context['record']['owner_id'])) {
                    $userIds[] = $context['record']['owner_id'];
                }
                break;

            case 'current_user':
                if (isset($context['triggered_by_user_id'])) {
                    $userIds[] = $context['triggered_by_user_id'];
                }
                break;

            case 'specific_users':
                $userIds = $config['user_ids'] ?? [];
                break;

            case 'role':
                // Get users by role
                if (isset($config['role_name'])) {
                    $users = DB::table('users')->whereIn('id', DB::table('model_has_roles')->where('role_id', DB::table('roles')->where('name', $config['role_name'])->value('id'))->pluck('model_id'))->pluck('id')->toArray();
                    $userIds = $users;
                }
                break;

            case 'all_admins':
                $users = DB::table('users')->whereIn('id', DB::table('model_has_roles')->where('role_id', DB::table('roles')->where('name', 'admin')->value('id'))->pluck('model_id'))->pluck('id')->toArray();
                $userIds = $users;
                break;

            default:
                $userIds = $config['user_ids'] ?? [];
        }

        return array_unique(array_filter($userIds));
    }

    protected function mapNotificationType(string $type): string
    {
        return match ($type) {
            'success' => Notification::TYPE_SYSTEM_ANNOUNCEMENT,
            'warning' => Notification::TYPE_REMINDER_FOLLOWUP,
            'error' => Notification::TYPE_TASK_OVERDUE,
            default => Notification::TYPE_SYSTEM_ANNOUNCEMENT,
        };
    }

    protected function replaceVariables(string $text, array $context): string
    {
        if (empty($text)) {
            return $text;
        }

        $replacements = [];

        // Record fields
        if (isset($context['record']) && is_array($context['record'])) {
            foreach ($context['record'] as $key => $value) {
                if (is_scalar($value)) {
                    $replacements["{{record.{$key}}}"] = (string) $value;
                    $replacements["{{\$record.{$key}}}"] = (string) $value;
                }
            }
        }

        // Module info
        if (isset($context['module']) && is_array($context['module'])) {
            $replacements['{{module.name}}'] = $context['module']['name'] ?? '';
            $replacements['{{module.api_name}}'] = $context['module']['api_name'] ?? '';
        }

        // User info
        if (isset($context['triggered_by_user'])) {
            $replacements['{{user.name}}'] = $context['triggered_by_user']['name'] ?? '';
            $replacements['{{user.email}}'] = $context['triggered_by_user']['email'] ?? '';
        }

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    public static function getConfigSchema(): array
    {
        return ['fields' => [
            [
                'name' => 'recipient_type',
                'label' => 'Send To',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'record_owner', 'label' => 'Record Owner'],
                    ['value' => 'current_user', 'label' => 'Current User'],
                    ['value' => 'specific_users', 'label' => 'Specific Users'],
                    ['value' => 'role', 'label' => 'Users with Role'],
                    ['value' => 'all_admins', 'label' => 'All Admins'],
                ],
                'default' => 'specific_users',
            ],
            [
                'name' => 'user_ids',
                'label' => 'Notify Users',
                'type' => 'user_multi_select',
                'required' => false,
                'visible_when' => ['recipient_type' => 'specific_users'],
            ],
            [
                'name' => 'role_name',
                'label' => 'Role',
                'type' => 'role_select',
                'required' => false,
                'visible_when' => ['recipient_type' => 'role'],
            ],
            [
                'name' => 'notification_type',
                'label' => 'Notification Type',
                'type' => 'select',
                'options' => [
                    ['value' => 'info', 'label' => 'Info'],
                    ['value' => 'success', 'label' => 'Success'],
                    ['value' => 'warning', 'label' => 'Warning'],
                    ['value' => 'error', 'label' => 'Error'],
                ],
                'default' => 'info',
            ],
            [
                'name' => 'title',
                'label' => 'Title',
                'type' => 'text',
                'required' => true,
                'supports_variables' => true,
            ],
            [
                'name' => 'message',
                'label' => 'Message',
                'type' => 'textarea',
                'required' => false,
                'supports_variables' => true,
            ],
            [
                'name' => 'link_to_record',
                'label' => 'Link to Record',
                'type' => 'checkbox',
                'default' => true,
            ],
        ]];
    }

    public function validate(array $config): array
    {
        $errors = [];

        $recipientType = $config['recipient_type'] ?? 'specific_users';

        if ($recipientType === 'specific_users' && empty($config['user_ids'])) {
            $errors['user_ids'] = 'At least one user is required when sending to specific users';
        }

        if ($recipientType === 'role' && empty($config['role_name'])) {
            $errors['role_name'] = 'Role is required when sending to users with role';
        }

        if (empty($config['title'])) {
            $errors['title'] = 'Title is required';
        }

        return $errors;
    }
}
