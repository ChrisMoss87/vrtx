<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    use HasFactory;

    // Action types
    public const ACTION_SEND_EMAIL = 'send_email';
    public const ACTION_CREATE_RECORD = 'create_record';
    public const ACTION_UPDATE_RECORD = 'update_record';
    public const ACTION_DELETE_RECORD = 'delete_record';
    public const ACTION_WEBHOOK = 'webhook';
    public const ACTION_ASSIGN_USER = 'assign_user';
    public const ACTION_ADD_TAG = 'add_tag';
    public const ACTION_REMOVE_TAG = 'remove_tag';
    public const ACTION_SEND_NOTIFICATION = 'send_notification';
    public const ACTION_DELAY = 'delay';
    public const ACTION_CONDITION = 'condition';
    public const ACTION_UPDATE_FIELD = 'update_field';
    public const ACTION_CREATE_TASK = 'create_task';
    public const ACTION_MOVE_STAGE = 'move_stage';
    public const ACTION_UPDATE_RELATED = 'update_related_record';

    protected $table = 'workflow_steps';

    protected $fillable = [
        'workflow_id',
        'order',
        'name',
        'action_type',
        'action_config',
        'conditions',
        'branch_id',
        'is_parallel',
        'continue_on_error',
        'retry_count',
        'retry_delay_seconds',
    ];

    protected $casts = [
        'workflow_id' => 'integer',
        'order' => 'integer',
        'action_config' => 'array',
        'conditions' => 'array',
        'is_parallel' => 'boolean',
        'continue_on_error' => 'boolean',
        'retry_count' => 'integer',
        'retry_delay_seconds' => 'integer',
    ];

    protected $attributes = [
        'order' => 0,
        'action_config' => '{}',
        'is_parallel' => false,
        'continue_on_error' => false,
        'retry_count' => 0,
        'retry_delay_seconds' => 60,
    ];

    /**
     * Get available action types.
     */
    public static function getActionTypes(): array
    {
        return [
            self::ACTION_SEND_EMAIL => [
                'label' => 'Send Email',
                'icon' => 'mail',
                'description' => 'Send an email to specified recipients',
                'category' => 'communication',
            ],
            self::ACTION_CREATE_RECORD => [
                'label' => 'Create Record',
                'icon' => 'plus-circle',
                'description' => 'Create a new record in any module',
                'category' => 'records',
            ],
            self::ACTION_UPDATE_RECORD => [
                'label' => 'Update Record',
                'icon' => 'edit',
                'description' => 'Update fields on the current or related record',
                'category' => 'records',
            ],
            self::ACTION_DELETE_RECORD => [
                'label' => 'Delete Record',
                'icon' => 'trash',
                'description' => 'Delete the current or related record',
                'category' => 'records',
            ],
            self::ACTION_UPDATE_FIELD => [
                'label' => 'Update Field',
                'icon' => 'edit-3',
                'description' => 'Update a specific field value',
                'category' => 'records',
            ],
            self::ACTION_WEBHOOK => [
                'label' => 'Call Webhook',
                'icon' => 'globe',
                'description' => 'Send data to an external URL',
                'category' => 'integration',
            ],
            self::ACTION_ASSIGN_USER => [
                'label' => 'Assign User',
                'icon' => 'user-plus',
                'description' => 'Assign the record to a user or team',
                'category' => 'assignment',
            ],
            self::ACTION_ADD_TAG => [
                'label' => 'Add Tag',
                'icon' => 'tag',
                'description' => 'Add a tag to the record',
                'category' => 'organization',
            ],
            self::ACTION_REMOVE_TAG => [
                'label' => 'Remove Tag',
                'icon' => 'x',
                'description' => 'Remove a tag from the record',
                'category' => 'organization',
            ],
            self::ACTION_SEND_NOTIFICATION => [
                'label' => 'Send Notification',
                'icon' => 'bell',
                'description' => 'Send an in-app notification to users',
                'category' => 'communication',
            ],
            self::ACTION_DELAY => [
                'label' => 'Wait / Delay',
                'icon' => 'clock',
                'description' => 'Wait for a specified time before continuing',
                'category' => 'flow',
            ],
            self::ACTION_CONDITION => [
                'label' => 'Condition Branch',
                'icon' => 'git-branch',
                'description' => 'Branch workflow based on conditions',
                'category' => 'flow',
            ],
            self::ACTION_CREATE_TASK => [
                'label' => 'Create Task',
                'icon' => 'check-square',
                'description' => 'Create a task assigned to a user',
                'category' => 'records',
            ],
            self::ACTION_MOVE_STAGE => [
                'label' => 'Move Pipeline Stage',
                'icon' => 'arrow-right',
                'description' => 'Move record to a different pipeline stage',
                'category' => 'pipeline',
            ],
            self::ACTION_UPDATE_RELATED => [
                'label' => 'Update Related Record',
                'icon' => 'link',
                'description' => 'Update fields on related parent or child records',
                'category' => 'records',
            ],
        ];
    }

    /**
     * Get the workflow this step belongs to.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the execution logs for this step.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(WorkflowStepLog::class, 'step_id');
    }

    /**
     * Get a human-readable description of what this step does.
     */
    public function getDescriptionAttribute(): string
    {
        $types = self::getActionTypes();
        $typeInfo = $types[$this->action_type] ?? null;

        if (!$typeInfo) {
            return "Unknown action: {$this->action_type}";
        }

        return $this->name ?? $typeInfo['label'];
    }

    /**
     * Check if this step should be executed based on its conditions.
     */
    public function shouldExecute(array $context): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        // Condition evaluation will be handled by the workflow engine
        return true;
    }
}
