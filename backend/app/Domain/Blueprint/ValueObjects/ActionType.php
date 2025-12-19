<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\ValueObjects;

/**
 * Represents the type of action to execute after a transition.
 */
enum ActionType: string
{
    case UPDATE_FIELD = 'update_field';
    case SEND_EMAIL = 'send_email';
    case SEND_NOTIFICATION = 'send_notification';
    case CREATE_TASK = 'create_task';
    case ASSIGN_USER = 'assign_user';
    case WEBHOOK = 'webhook';
    case TRIGGER_WORKFLOW = 'trigger_workflow';

    public function label(): string
    {
        return match ($this) {
            self::UPDATE_FIELD => 'Update Field',
            self::SEND_EMAIL => 'Send Email',
            self::SEND_NOTIFICATION => 'Send Notification',
            self::CREATE_TASK => 'Create Task',
            self::ASSIGN_USER => 'Assign User',
            self::WEBHOOK => 'Call Webhook',
            self::TRIGGER_WORKFLOW => 'Trigger Workflow',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::UPDATE_FIELD => 'pencil',
            self::SEND_EMAIL => 'mail',
            self::SEND_NOTIFICATION => 'bell',
            self::CREATE_TASK => 'clipboard-list',
            self::ASSIGN_USER => 'user',
            self::WEBHOOK => 'globe',
            self::TRIGGER_WORKFLOW => 'play',
        };
    }

    /**
     * Get all action types as options array for UI.
     */
    public static function options(): array
    {
        return array_map(
            fn(self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases()
        );
    }
}
