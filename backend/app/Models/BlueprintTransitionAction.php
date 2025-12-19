<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlueprintTransitionAction extends Model
{
    use HasFactory;

    // Action types (mirrors workflow action types)
    public const TYPE_SEND_EMAIL = 'send_email';
    public const TYPE_UPDATE_FIELD = 'update_field';
    public const TYPE_CREATE_RECORD = 'create_record';
    public const TYPE_CREATE_TASK = 'create_task';
    public const TYPE_WEBHOOK = 'webhook';
    public const TYPE_NOTIFY_USER = 'notify_user';
    public const TYPE_ADD_TAG = 'add_tag';
    public const TYPE_REMOVE_TAG = 'remove_tag';
    public const TYPE_SCHEDULE_FOLLOWUP = 'schedule_followup';
    public const TYPE_CONVERT_RECORD = 'convert_record';
    public const TYPE_UPDATE_RELATED = 'update_related';
    public const TYPE_SLACK_MESSAGE = 'slack_message';

    protected $fillable = [
        'transition_id',
        'type',
        'config',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'transition_id' => 'integer',
        'config' => 'array',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'display_order' => 0,
        'is_active' => true,
    ];

    /**
     * Get the transition this action belongs to.
     */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(BlueprintTransition::class, 'transition_id');
    }

    /**
     * Get logs for this action.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(BlueprintActionLog::class, 'action_id');
    }

    /**
     * Get available action types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_SEND_EMAIL => [
                'label' => 'Send Email',
                'description' => 'Send an email to specified recipients',
                'icon' => 'mail',
                'category' => 'communication',
            ],
            self::TYPE_UPDATE_FIELD => [
                'label' => 'Update Field',
                'description' => 'Update a field value on the record',
                'icon' => 'edit',
                'category' => 'record',
            ],
            self::TYPE_CREATE_RECORD => [
                'label' => 'Create Record',
                'description' => 'Create a new record in a module',
                'icon' => 'plus-circle',
                'category' => 'record',
            ],
            self::TYPE_CREATE_TASK => [
                'label' => 'Create Task',
                'description' => 'Create a follow-up task',
                'icon' => 'check-square',
                'category' => 'record',
            ],
            self::TYPE_WEBHOOK => [
                'label' => 'Call Webhook',
                'description' => 'Send data to an external URL',
                'icon' => 'globe',
                'category' => 'integration',
            ],
            self::TYPE_NOTIFY_USER => [
                'label' => 'Notify User',
                'description' => 'Send an in-app notification',
                'icon' => 'bell',
                'category' => 'communication',
            ],
            self::TYPE_ADD_TAG => [
                'label' => 'Add Tag',
                'description' => 'Add tags to the record',
                'icon' => 'tag',
                'category' => 'record',
            ],
            self::TYPE_REMOVE_TAG => [
                'label' => 'Remove Tag',
                'description' => 'Remove tags from the record',
                'icon' => 'x-circle',
                'category' => 'record',
            ],
            self::TYPE_SCHEDULE_FOLLOWUP => [
                'label' => 'Schedule Follow-up',
                'description' => 'Schedule a follow-up date/time',
                'icon' => 'calendar',
                'category' => 'scheduling',
            ],
            self::TYPE_CONVERT_RECORD => [
                'label' => 'Convert Record',
                'description' => 'Convert the record to another module',
                'icon' => 'shuffle',
                'category' => 'record',
            ],
            self::TYPE_UPDATE_RELATED => [
                'label' => 'Update Related Records',
                'description' => 'Update fields on related records',
                'icon' => 'link',
                'category' => 'record',
            ],
            self::TYPE_SLACK_MESSAGE => [
                'label' => 'Send Slack Message',
                'description' => 'Post a message to Slack',
                'icon' => 'message-square',
                'category' => 'integration',
            ],
        ];
    }

    /**
     * Scope to only active actions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
