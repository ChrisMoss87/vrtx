<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AbTest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'entity_type',
        'entity_id',
        'status',
        'goal',
        'min_sample_size',
        'confidence_level',
        'auto_select_winner',
        'winner_variant_id',
        'started_at',
        'ended_at',
        'scheduled_end_at',
        'created_by',
    ];

    protected $casts = [
        'auto_select_winner' => 'boolean',
        'confidence_level' => 'decimal:2',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
    ];

    public const TYPE_EMAIL_SUBJECT = 'email_subject';
    public const TYPE_EMAIL_CONTENT = 'email_content';
    public const TYPE_CTA_BUTTON = 'cta_button';
    public const TYPE_SEND_TIME = 'send_time';
    public const TYPE_FORM_LAYOUT = 'form_layout';

    public const ENTITY_EMAIL_TEMPLATE = 'email_template';
    public const ENTITY_CAMPAIGN = 'campaign';
    public const ENTITY_WEB_FORM = 'web_form';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';

    public const GOAL_CONVERSION = 'conversion';
    public const GOAL_CLICK_RATE = 'click_rate';
    public const GOAL_OPEN_RATE = 'open_rate';

    public function variants(): HasMany
    {
        return $this->hasMany(AbTestVariant::class, 'test_id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function winnerVariant(): BelongsTo
    {
        return $this->belongsTo(AbTestVariant::class, 'winner_variant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function start(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    public function pause(): void
    {
        $this->update(['status' => self::STATUS_PAUSED]);
    }

    public function resume(): void
    {
        $this->update(['status' => self::STATUS_RUNNING]);
    }

    public function complete(?int $winnerVariantId = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'ended_at' => now(),
            'winner_variant_id' => $winnerVariantId,
        ]);
    }

    public function getTotalImpressions(): int
    {
        return $this->variants->sum(function ($variant) {
            return $variant->results->sum('impressions');
        });
    }

    public function getTotalConversions(): int
    {
        return $this->variants->sum(function ($variant) {
            return $variant->results->sum('conversions');
        });
    }

    public static function getTypes(): array
    {
        return [
            self::TYPE_EMAIL_SUBJECT => 'Email Subject Line',
            self::TYPE_EMAIL_CONTENT => 'Email Content',
            self::TYPE_CTA_BUTTON => 'CTA Button',
            self::TYPE_SEND_TIME => 'Send Time',
            self::TYPE_FORM_LAYOUT => 'Form Layout',
        ];
    }

    public static function getEntityTypes(): array
    {
        return [
            self::ENTITY_EMAIL_TEMPLATE => 'Email Template',
            self::ENTITY_CAMPAIGN => 'Campaign',
            self::ENTITY_WEB_FORM => 'Web Form',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_RUNNING => 'Running',
            self::STATUS_PAUSED => 'Paused',
            self::STATUS_COMPLETED => 'Completed',
        ];
    }

    public static function getGoals(): array
    {
        return [
            self::GOAL_CONVERSION => 'Conversion Rate',
            self::GOAL_CLICK_RATE => 'Click Rate',
            self::GOAL_OPEN_RATE => 'Open Rate',
        ];
    }
}
