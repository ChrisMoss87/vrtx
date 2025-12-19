<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LookalikeAudience extends Model
{
    use HasFactory;

    // Source types
    public const SOURCE_SAVED_SEARCH = 'saved_search';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_SEGMENT = 'segment';

    // Statuses
    public const STATUS_DRAFT = 'draft';
    public const STATUS_BUILDING = 'building';
    public const STATUS_READY = 'ready';
    public const STATUS_EXPIRED = 'expired';

    // Refresh frequencies
    public const REFRESH_DAILY = 'daily';
    public const REFRESH_WEEKLY = 'weekly';
    public const REFRESH_MONTHLY = 'monthly';

    // Match criteria types
    public const CRITERIA_INDUSTRY = 'industry';
    public const CRITERIA_COMPANY_SIZE = 'company_size';
    public const CRITERIA_LOCATION = 'location';
    public const CRITERIA_BEHAVIOR = 'behavior';
    public const CRITERIA_TECHNOLOGY = 'technology';
    public const CRITERIA_ENGAGEMENT = 'engagement';
    public const CRITERIA_PURCHASE = 'purchase';

    protected $fillable = [
        'name',
        'description',
        'source_type',
        'source_id',
        'source_criteria',
        'match_criteria',
        'weights',
        'min_similarity_score',
        'size_limit',
        'status',
        'last_built_at',
        'build_duration_seconds',
        'source_count',
        'match_count',
        'auto_refresh',
        'refresh_frequency',
        'next_refresh_at',
        'export_destinations',
        'last_exported_at',
        'created_by',
    ];

    protected $casts = [
        'source_criteria' => 'array',
        'match_criteria' => 'array',
        'weights' => 'array',
        'min_similarity_score' => 'decimal:2',
        'auto_refresh' => 'boolean',
        'export_destinations' => 'array',
        'last_built_at' => 'datetime',
        'next_refresh_at' => 'datetime',
        'last_exported_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(LookalikeMatch::class, 'audience_id');
    }

    public function buildJobs(): HasMany
    {
        return $this->hasMany(LookalikeBuildJob::class, 'audience_id');
    }

    public function exportLogs(): HasMany
    {
        return $this->hasMany(LookalikeExportLog::class, 'audience_id');
    }

    public function topMatches(int $limit = 100): HasMany
    {
        return $this->matches()
            ->orderByDesc('similarity_score')
            ->limit($limit);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isBuilding(): bool
    {
        return $this->status === self::STATUS_BUILDING;
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function markAsBuilding(): void
    {
        $this->update(['status' => self::STATUS_BUILDING]);
    }

    public function markAsReady(int $matchCount, int $duration): void
    {
        $this->update([
            'status' => self::STATUS_READY,
            'match_count' => $matchCount,
            'last_built_at' => now(),
            'build_duration_seconds' => $duration,
            'next_refresh_at' => $this->calculateNextRefresh(),
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => self::STATUS_EXPIRED]);
    }

    protected function calculateNextRefresh(): ?\DateTime
    {
        if (!$this->auto_refresh || !$this->refresh_frequency) {
            return null;
        }

        return match ($this->refresh_frequency) {
            self::REFRESH_DAILY => now()->addDay(),
            self::REFRESH_WEEKLY => now()->addWeek(),
            self::REFRESH_MONTHLY => now()->addMonth(),
            default => null,
        };
    }

    public static function getSourceTypes(): array
    {
        return [
            self::SOURCE_SAVED_SEARCH => 'Saved Search',
            self::SOURCE_MANUAL => 'Manual Selection',
            self::SOURCE_SEGMENT => 'Segment',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_BUILDING => 'Building',
            self::STATUS_READY => 'Ready',
            self::STATUS_EXPIRED => 'Expired',
        ];
    }

    public static function getCriteriaTypes(): array
    {
        return [
            self::CRITERIA_INDUSTRY => 'Industry',
            self::CRITERIA_COMPANY_SIZE => 'Company Size',
            self::CRITERIA_LOCATION => 'Location',
            self::CRITERIA_BEHAVIOR => 'Behavior Patterns',
            self::CRITERIA_TECHNOLOGY => 'Technology Usage',
            self::CRITERIA_ENGAGEMENT => 'Engagement Level',
            self::CRITERIA_PURCHASE => 'Purchase History',
        ];
    }

    public static function getRefreshFrequencies(): array
    {
        return [
            self::REFRESH_DAILY => 'Daily',
            self::REFRESH_WEEKLY => 'Weekly',
            self::REFRESH_MONTHLY => 'Monthly',
        ];
    }
}
