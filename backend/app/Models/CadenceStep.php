<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CadenceStep extends Model
{
    use HasFactory, BelongsToTenant;

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_CALL = 'call';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_LINKEDIN = 'linkedin';
    public const CHANNEL_TASK = 'task';
    public const CHANNEL_WAIT = 'wait';

    public const CHANNELS = [
        self::CHANNEL_EMAIL => 'Email',
        self::CHANNEL_CALL => 'Call',
        self::CHANNEL_SMS => 'SMS',
        self::CHANNEL_LINKEDIN => 'LinkedIn',
        self::CHANNEL_TASK => 'Task',
        self::CHANNEL_WAIT => 'Wait',
    ];

    public const DELAY_IMMEDIATE = 'immediate';
    public const DELAY_DAYS = 'days';
    public const DELAY_HOURS = 'hours';
    public const DELAY_BUSINESS_DAYS = 'business_days';

    public const LINKEDIN_CONNECTION_REQUEST = 'connection_request';
    public const LINKEDIN_MESSAGE = 'message';
    public const LINKEDIN_VIEW_PROFILE = 'view_profile';
    public const LINKEDIN_ENGAGE = 'engage';

    protected $fillable = [
        'cadence_id',
        'step_order',
        'name',
        'channel',
        'delay_type',
        'delay_value',
        'preferred_time',
        'timezone',
        'subject',
        'content',
        'template_id',
        'conditions',
        'on_reply_goto_step',
        'on_click_goto_step',
        'on_no_response_goto_step',
        'is_ab_test',
        'ab_variant_of',
        'ab_percentage',
        'linkedin_action',
        'task_type',
        'task_assigned_to',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_ab_test' => 'boolean',
        'is_active' => 'boolean',
        'preferred_time' => 'datetime:H:i',
    ];

    public function cadence(): BelongsTo
    {
        return $this->belongsTo(Cadence::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailCampaignTemplate::class, 'template_id');
    }

    public function onReplyStep(): BelongsTo
    {
        return $this->belongsTo(CadenceStep::class, 'on_reply_goto_step');
    }

    public function onClickStep(): BelongsTo
    {
        return $this->belongsTo(CadenceStep::class, 'on_click_goto_step');
    }

    public function onNoResponseStep(): BelongsTo
    {
        return $this->belongsTo(CadenceStep::class, 'on_no_response_goto_step');
    }

    public function parentVariant(): BelongsTo
    {
        return $this->belongsTo(CadenceStep::class, 'ab_variant_of');
    }

    public function abVariants(): HasMany
    {
        return $this->hasMany(CadenceStep::class, 'ab_variant_of');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(CadenceStepExecution::class, 'step_id');
    }

    public function taskAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'task_assigned_to');
    }

    public function getDelayInSeconds(): int
    {
        return match ($this->delay_type) {
            self::DELAY_IMMEDIATE => 0,
            self::DELAY_HOURS => $this->delay_value * 3600,
            self::DELAY_DAYS => $this->delay_value * 86400,
            self::DELAY_BUSINESS_DAYS => $this->calculateBusinessDaysInSeconds($this->delay_value),
            default => 0,
        };
    }

    private function calculateBusinessDaysInSeconds(int $days): int
    {
        // Rough approximation: 7 calendar days = 5 business days
        return (int) ($days * 1.4 * 86400);
    }

    public function getDisplayName(): string
    {
        return $this->name ?? "Step {$this->step_order}: " . self::CHANNELS[$this->channel];
    }
}
