<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordingStep extends Model
{
    protected $fillable = [
        'recording_id',
        'step_order',
        'action_type',
        'target_module',
        'target_record_id',
        'action_data',
        'parameterized_data',
        'is_parameterized',
        'captured_at',
    ];

    protected $casts = [
        'action_data' => 'array',
        'parameterized_data' => 'array',
        'is_parameterized' => 'boolean',
        'captured_at' => 'datetime',
    ];

    // Action types
    public const ACTION_CREATE_RECORD = 'create_record';
    public const ACTION_UPDATE_FIELD = 'update_field';
    public const ACTION_CHANGE_STAGE = 'change_stage';
    public const ACTION_SEND_EMAIL = 'send_email';
    public const ACTION_CREATE_TASK = 'create_task';
    public const ACTION_ADD_NOTE = 'add_note';
    public const ACTION_ADD_TAG = 'add_tag';
    public const ACTION_REMOVE_TAG = 'remove_tag';
    public const ACTION_ASSIGN_USER = 'assign_user';
    public const ACTION_LOG_ACTIVITY = 'log_activity';

    public static function getActionTypes(): array
    {
        return [
            self::ACTION_CREATE_RECORD => 'Create Record',
            self::ACTION_UPDATE_FIELD => 'Update Field',
            self::ACTION_CHANGE_STAGE => 'Change Stage',
            self::ACTION_SEND_EMAIL => 'Send Email',
            self::ACTION_CREATE_TASK => 'Create Task',
            self::ACTION_ADD_NOTE => 'Add Note',
            self::ACTION_ADD_TAG => 'Add Tag',
            self::ACTION_REMOVE_TAG => 'Remove Tag',
            self::ACTION_ASSIGN_USER => 'Assign User',
            self::ACTION_LOG_ACTIVITY => 'Log Activity',
        ];
    }

    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }

    public function getActionLabel(): string
    {
        return self::getActionTypes()[$this->action_type] ?? $this->action_type;
    }

    public function getDescription(): string
    {
        $data = $this->action_data;

        return match ($this->action_type) {
            self::ACTION_CREATE_RECORD => sprintf('Created %s record', $this->target_module ?? 'new'),
            self::ACTION_UPDATE_FIELD => sprintf('Updated "%s" to "%s"', $data['field'] ?? 'field', $data['new_value'] ?? 'value'),
            self::ACTION_CHANGE_STAGE => sprintf('Changed stage to "%s"', $data['new_stage'] ?? 'stage'),
            self::ACTION_SEND_EMAIL => sprintf('Sent email: "%s"', $data['subject'] ?? 'Email'),
            self::ACTION_CREATE_TASK => sprintf('Created task: "%s"', $data['title'] ?? 'Task'),
            self::ACTION_ADD_NOTE => 'Added note',
            self::ACTION_ADD_TAG => sprintf('Added tag "%s"', $data['tag'] ?? 'tag'),
            self::ACTION_REMOVE_TAG => sprintf('Removed tag "%s"', $data['tag'] ?? 'tag'),
            self::ACTION_ASSIGN_USER => sprintf('Assigned to %s', $data['user_name'] ?? 'user'),
            self::ACTION_LOG_ACTIVITY => sprintf('Logged %s activity', $data['activity_type'] ?? ''),
            default => 'Performed action',
        };
    }

    public function parameterize(string $field, string $referenceType, ?string $referenceField = null): void
    {
        $parameterized = $this->parameterized_data ?? $this->action_data;

        // Mark the field as parameterized
        $parameterized[$field] = [
            'type' => 'reference',
            'reference_type' => $referenceType, // field, current_user, owner, etc.
            'reference_field' => $referenceField,
            'original_value' => $this->action_data[$field] ?? null,
        ];

        $this->update([
            'parameterized_data' => $parameterized,
            'is_parameterized' => true,
        ]);
    }

    public function resetParameterization(): void
    {
        $this->update([
            'parameterized_data' => null,
            'is_parameterized' => false,
        ]);
    }

    public function getEffectiveData(): array
    {
        return $this->is_parameterized ? ($this->parameterized_data ?? $this->action_data) : $this->action_data;
    }

    public function toWorkflowStep(): array
    {
        $data = $this->getEffectiveData();

        return match ($this->action_type) {
            self::ACTION_UPDATE_FIELD => [
                'type' => 'update_field',
                'config' => [
                    'field' => $data['field'] ?? null,
                    'value' => $data['new_value'] ?? null,
                ],
            ],
            self::ACTION_CHANGE_STAGE => [
                'type' => 'move_stage',
                'config' => [
                    'stage_id' => $data['stage_id'] ?? null,
                ],
            ],
            self::ACTION_SEND_EMAIL => [
                'type' => 'send_email',
                'config' => [
                    'template_id' => $data['template_id'] ?? null,
                    'recipient_type' => $data['recipient_type'] ?? 'record_email',
                    'subject' => $data['subject'] ?? null,
                ],
            ],
            self::ACTION_CREATE_TASK => [
                'type' => 'create_task',
                'config' => [
                    'title' => $data['title'] ?? null,
                    'due_days' => $data['due_days'] ?? 3,
                    'priority' => $data['priority'] ?? 'normal',
                ],
            ],
            self::ACTION_ADD_TAG => [
                'type' => 'add_tag',
                'config' => [
                    'tag' => $data['tag'] ?? null,
                ],
            ],
            self::ACTION_REMOVE_TAG => [
                'type' => 'remove_tag',
                'config' => [
                    'tag' => $data['tag'] ?? null,
                ],
            ],
            self::ACTION_ASSIGN_USER => [
                'type' => 'assign_user',
                'config' => [
                    'user_id' => $data['user_id'] ?? null,
                    'assignment_type' => $data['assignment_type'] ?? 'specific',
                ],
            ],
            default => [
                'type' => $this->action_type,
                'config' => $data,
            ],
        };
    }
}
