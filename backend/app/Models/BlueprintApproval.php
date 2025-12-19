<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlueprintApproval extends Model
{
    use HasFactory;

    // Approval types
    public const TYPE_SPECIFIC_USERS = 'specific_users';
    public const TYPE_ROLE_BASED = 'role_based';
    public const TYPE_MANAGER = 'manager';
    public const TYPE_FIELD_VALUE = 'field_value';

    // Escalation types
    public const ESCALATION_MANAGER = 'manager';
    public const ESCALATION_SPECIFIC_USER = 'specific_user';
    public const ESCALATION_ROLE = 'role';

    protected $fillable = [
        'transition_id',
        'approval_type',
        'config',
        'require_all',
        'auto_reject_days',
        'escalation_hours',
        'escalation_type',
        'escalation_config',
        'reminder_hours',
        'max_reminders',
        'notify_on_pending',
        'notify_on_complete',
    ];

    protected $casts = [
        'transition_id' => 'integer',
        'config' => 'array',
        'require_all' => 'boolean',
        'auto_reject_days' => 'integer',
        'escalation_hours' => 'integer',
        'escalation_config' => 'array',
        'reminder_hours' => 'integer',
        'max_reminders' => 'integer',
        'notify_on_pending' => 'boolean',
        'notify_on_complete' => 'boolean',
    ];

    protected $attributes = [
        'require_all' => false,
        'notify_on_pending' => true,
        'notify_on_complete' => true,
    ];

    /**
     * Get the transition this approval is for.
     */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(BlueprintTransition::class, 'transition_id');
    }

    /**
     * Get approval requests.
     */
    public function requests(): HasMany
    {
        return $this->hasMany(BlueprintApprovalRequest::class, 'approval_id');
    }

    /**
     * Get available approval types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_SPECIFIC_USERS => [
                'label' => 'Specific Users',
                'description' => 'Approve by specific users',
                'config_fields' => ['user_ids'],
            ],
            self::TYPE_ROLE_BASED => [
                'label' => 'Role Based',
                'description' => 'Approve by users with specific roles',
                'config_fields' => ['role_ids'],
            ],
            self::TYPE_MANAGER => [
                'label' => 'Manager',
                'description' => 'Approve by the record owner\'s manager',
                'config_fields' => [],
            ],
            self::TYPE_FIELD_VALUE => [
                'label' => 'Field Value',
                'description' => 'Approve by user specified in a lookup field',
                'config_fields' => ['field_id'],
            ],
        ];
    }

    /**
     * Get the user IDs who can approve (for specific_users type).
     */
    public function getSpecificUserIds(): array
    {
        if ($this->approval_type !== self::TYPE_SPECIFIC_USERS) {
            return [];
        }

        return $this->config['user_ids'] ?? [];
    }

    /**
     * Get the role IDs who can approve (for role_based type).
     */
    public function getRoleIds(): array
    {
        if ($this->approval_type !== self::TYPE_ROLE_BASED) {
            return [];
        }

        return $this->config['role_ids'] ?? [];
    }

    /**
     * Get the field ID for field_value type.
     */
    public function getApproverFieldId(): ?int
    {
        if ($this->approval_type !== self::TYPE_FIELD_VALUE) {
            return null;
        }

        return $this->config['field_id'] ?? null;
    }

    /**
     * Check if escalation is configured.
     */
    public function hasEscalation(): bool
    {
        return $this->escalation_hours !== null && $this->escalation_type !== null;
    }

    /**
     * Check if reminders are configured.
     */
    public function hasReminders(): bool
    {
        return $this->reminder_hours !== null && $this->reminder_hours > 0;
    }

    /**
     * Check if auto-reject is configured.
     */
    public function hasAutoReject(): bool
    {
        return $this->auto_reject_days !== null && $this->auto_reject_days > 0;
    }

    /**
     * Get the escalation target user ID.
     */
    public function getEscalationTargetUserId(): ?int
    {
        if ($this->escalation_type === self::ESCALATION_SPECIFIC_USER) {
            return $this->escalation_config['user_id'] ?? null;
        }

        return null;
    }

    /**
     * Get the escalation target role IDs.
     */
    public function getEscalationRoleIds(): array
    {
        if ($this->escalation_type === self::ESCALATION_ROLE) {
            return $this->escalation_config['role_ids'] ?? [];
        }

        return [];
    }

    /**
     * Get available escalation types.
     */
    public static function getEscalationTypes(): array
    {
        return [
            self::ESCALATION_MANAGER => [
                'label' => 'Manager',
                'description' => 'Escalate to the original approver\'s manager',
            ],
            self::ESCALATION_SPECIFIC_USER => [
                'label' => 'Specific User',
                'description' => 'Escalate to a specific user',
                'config_fields' => ['user_id'],
            ],
            self::ESCALATION_ROLE => [
                'label' => 'Role',
                'description' => 'Escalate to users with specific roles',
                'config_fields' => ['role_ids'],
            ],
        ];
    }
}
