<?php

declare(strict_types=1);

namespace App\Domain\Workflow\ValueObjects;

/**
 * Enum representing workflow step action types.
 */
enum ActionType: string
{
    case SEND_EMAIL = 'send_email';
    case CREATE_RECORD = 'create_record';
    case UPDATE_RECORD = 'update_record';
    case DELETE_RECORD = 'delete_record';
    case UPDATE_FIELD = 'update_field';
    case WEBHOOK = 'webhook';
    case ASSIGN_USER = 'assign_user';
    case ADD_TAG = 'add_tag';
    case REMOVE_TAG = 'remove_tag';
    case SEND_NOTIFICATION = 'send_notification';
    case DELAY = 'delay';
    case CONDITION = 'condition';
    case CREATE_TASK = 'create_task';
    case MOVE_STAGE = 'move_stage';
    case UPDATE_RELATED = 'update_related_record';

    /**
     * Get human-readable label for this action type.
     */
    public function label(): string
    {
        return match ($this) {
            self::SEND_EMAIL => 'Send Email',
            self::CREATE_RECORD => 'Create Record',
            self::UPDATE_RECORD => 'Update Record',
            self::DELETE_RECORD => 'Delete Record',
            self::UPDATE_FIELD => 'Update Field',
            self::WEBHOOK => 'Call Webhook',
            self::ASSIGN_USER => 'Assign User',
            self::ADD_TAG => 'Add Tag',
            self::REMOVE_TAG => 'Remove Tag',
            self::SEND_NOTIFICATION => 'Send Notification',
            self::DELAY => 'Wait / Delay',
            self::CONDITION => 'Condition Branch',
            self::CREATE_TASK => 'Create Task',
            self::MOVE_STAGE => 'Move Pipeline Stage',
            self::UPDATE_RELATED => 'Update Related Record',
        };
    }

    /**
     * Get description for this action type.
     */
    public function description(): string
    {
        return match ($this) {
            self::SEND_EMAIL => 'Send an email to specified recipients',
            self::CREATE_RECORD => 'Create a new record in any module',
            self::UPDATE_RECORD => 'Update fields on the current or related record',
            self::DELETE_RECORD => 'Delete the current or related record',
            self::UPDATE_FIELD => 'Update a specific field value',
            self::WEBHOOK => 'Send data to an external URL',
            self::ASSIGN_USER => 'Assign the record to a user or team',
            self::ADD_TAG => 'Add a tag to the record',
            self::REMOVE_TAG => 'Remove a tag from the record',
            self::SEND_NOTIFICATION => 'Send an in-app notification to users',
            self::DELAY => 'Wait for a specified time before continuing',
            self::CONDITION => 'Branch workflow based on conditions',
            self::CREATE_TASK => 'Create a task assigned to a user',
            self::MOVE_STAGE => 'Move record to a different pipeline stage',
            self::UPDATE_RELATED => 'Update fields on related parent or child records',
        };
    }

    /**
     * Get icon name for this action type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::SEND_EMAIL => 'mail',
            self::CREATE_RECORD => 'plus-circle',
            self::UPDATE_RECORD => 'edit',
            self::DELETE_RECORD => 'trash',
            self::UPDATE_FIELD => 'edit-3',
            self::WEBHOOK => 'globe',
            self::ASSIGN_USER => 'user-plus',
            self::ADD_TAG => 'tag',
            self::REMOVE_TAG => 'x',
            self::SEND_NOTIFICATION => 'bell',
            self::DELAY => 'clock',
            self::CONDITION => 'git-branch',
            self::CREATE_TASK => 'check-square',
            self::MOVE_STAGE => 'arrow-right',
            self::UPDATE_RELATED => 'link',
        };
    }

    /**
     * Get category for this action type.
     */
    public function category(): string
    {
        return match ($this) {
            self::SEND_EMAIL,
            self::SEND_NOTIFICATION => 'communication',
            self::CREATE_RECORD,
            self::UPDATE_RECORD,
            self::DELETE_RECORD,
            self::UPDATE_FIELD,
            self::CREATE_TASK,
            self::UPDATE_RELATED => 'records',
            self::WEBHOOK => 'integration',
            self::ASSIGN_USER => 'assignment',
            self::ADD_TAG,
            self::REMOVE_TAG => 'organization',
            self::DELAY,
            self::CONDITION => 'flow',
            self::MOVE_STAGE => 'pipeline',
        };
    }

    /**
     * Check if this action type is a flow control action.
     */
    public function isFlowControl(): bool
    {
        return in_array($this, [self::DELAY, self::CONDITION]);
    }

    /**
     * Check if this action type affects records.
     */
    public function affectsRecords(): bool
    {
        return in_array($this, [
            self::CREATE_RECORD,
            self::UPDATE_RECORD,
            self::DELETE_RECORD,
            self::UPDATE_FIELD,
            self::CREATE_TASK,
            self::UPDATE_RELATED,
            self::MOVE_STAGE,
        ]);
    }

    /**
     * Get all available action types as an array.
     *
     * @return array<string, array{label: string, description: string, icon: string, category: string}>
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = [
                'label' => $case->label(),
                'description' => $case->description(),
                'icon' => $case->icon(),
                'category' => $case->category(),
            ];
        }
        return $result;
    }
}
