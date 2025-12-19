<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_EMAIL = 'email';
    public const TYPE_DRIP = 'drip';
    public const TYPE_EVENT = 'event';
    public const TYPE_PRODUCT_LAUNCH = 'product_launch';
    public const TYPE_NEWSLETTER = 'newsletter';
    public const TYPE_RE_ENGAGEMENT = 're_engagement';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'name',
        'description',
        'type',
        'status',
        'module_id',
        'start_date',
        'end_date',
        'budget',
        'spent',
        'settings',
        'goals',
        'created_by',
        'owner_id',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'budget' => 'decimal:2',
        'spent' => 'decimal:2',
        'settings' => 'array',
        'goals' => 'array',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'settings' => '{}',
        'goals' => '[]',
        'spent' => 0,
    ];

    public static function getTypes(): array
    {
        return [
            self::TYPE_EMAIL => 'Email Campaign',
            self::TYPE_DRIP => 'Drip Sequence',
            self::TYPE_EVENT => 'Event Promotion',
            self::TYPE_PRODUCT_LAUNCH => 'Product Launch',
            self::TYPE_NEWSLETTER => 'Newsletter',
            self::TYPE_RE_ENGAGEMENT => 'Re-engagement',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PAUSED => 'Paused',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(CampaignAudience::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(CampaignAsset::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CampaignSend::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(CampaignMetric::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(CampaignConversion::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function canBeStarted(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED, self::STATUS_PAUSED]);
    }

    public function canBePaused(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getTotalRecipientsAttribute(): int
    {
        return $this->audiences()->sum('contact_count');
    }

    public function getTotalSendsAttribute(): int
    {
        return $this->sends()->count();
    }

    public function getOpenRateAttribute(): float
    {
        $delivered = $this->sends()->where('status', 'delivered')->count();
        if ($delivered === 0) return 0;

        $opened = $this->sends()->whereNotNull('opened_at')->count();
        return round(($opened / $delivered) * 100, 2);
    }

    public function getClickRateAttribute(): float
    {
        $delivered = $this->sends()->where('status', 'delivered')->count();
        if ($delivered === 0) return 0;

        $clicked = $this->sends()->whereNotNull('clicked_at')->count();
        return round(($clicked / $delivered) * 100, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
