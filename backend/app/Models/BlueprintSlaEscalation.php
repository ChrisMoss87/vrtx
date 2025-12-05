<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlueprintSlaEscalation extends Model
{
    use HasFactory;

    protected $table = 'blueprint_sla_escalations';

    // Trigger types
    public const TRIGGER_APPROACHING = 'approaching';
    public const TRIGGER_BREACHED = 'breached';

    // Action types (subset of transition actions)
    public const ACTION_SEND_EMAIL = 'send_email';
    public const ACTION_UPDATE_FIELD = 'update_field';
    public const ACTION_CREATE_TASK = 'create_task';
    public const ACTION_NOTIFY_USER = 'notify_user';

    protected $fillable = [
        'sla_id',
        'trigger_type',
        'trigger_value',
        'action_type',
        'config',
        'display_order',
    ];

    protected $casts = [
        'sla_id' => 'integer',
        'trigger_value' => 'integer',
        'config' => 'array',
        'display_order' => 'integer',
    ];

    protected $attributes = [
        'display_order' => 0,
    ];

    /**
     * Get the SLA this escalation belongs to.
     */
    public function sla(): BelongsTo
    {
        return $this->belongsTo(BlueprintSla::class, 'sla_id');
    }

    /**
     * Get logs for this escalation.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(BlueprintSlaEscalationLog::class, 'escalation_id');
    }

    /**
     * Get available trigger types.
     */
    public static function getTriggerTypes(): array
    {
        return [
            self::TRIGGER_APPROACHING => [
                'label' => 'Approaching SLA',
                'description' => 'Trigger when a percentage of SLA time has elapsed',
                'requires_value' => true,
            ],
            self::TRIGGER_BREACHED => [
                'label' => 'SLA Breached',
                'description' => 'Trigger when SLA is violated',
                'requires_value' => false,
            ],
        ];
    }

    /**
     * Get available action types.
     */
    public static function getActionTypes(): array
    {
        return [
            self::ACTION_SEND_EMAIL => [
                'label' => 'Send Email',
                'description' => 'Send an email notification',
            ],
            self::ACTION_UPDATE_FIELD => [
                'label' => 'Update Field',
                'description' => 'Update a field value',
            ],
            self::ACTION_CREATE_TASK => [
                'label' => 'Create Task',
                'description' => 'Create a follow-up task',
            ],
            self::ACTION_NOTIFY_USER => [
                'label' => 'Notify User',
                'description' => 'Send an in-app notification',
            ],
        ];
    }
}
