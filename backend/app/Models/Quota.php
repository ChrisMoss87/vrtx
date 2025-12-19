<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quota extends Model
{
    use HasFactory;
    public const METRIC_REVENUE = 'revenue';
    public const METRIC_DEALS = 'deals';
    public const METRIC_LEADS = 'leads';
    public const METRIC_CALLS = 'calls';
    public const METRIC_MEETINGS = 'meetings';
    public const METRIC_ACTIVITIES = 'activities';
    public const METRIC_CUSTOM = 'custom';

    protected $fillable = [
        'period_id',
        'user_id',
        'team_id',
        'metric_type',
        'metric_field',
        'module_api_name',
        'target_value',
        'currency',
        'current_value',
        'attainment_percent',
        'created_by',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'attainment_percent' => 'decimal:2',
    ];

    protected $appends = ['gap_to_target', 'is_achieved', 'pace_required'];

    // Relationships
    public function period(): BelongsTo
    {
        return $this->belongsTo(QuotaPeriod::class, 'period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(QuotaSnapshot::class);
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForPeriod($query, int $periodId)
    {
        return $query->where('period_id', $periodId);
    }

    public function scopeMetricType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    public function scopeActive($query)
    {
        return $query->whereHas('period', function ($q) {
            $q->active()->current();
        });
    }

    // Computed attributes
    public function getGapToTargetAttribute(): float
    {
        return $this->target_value - $this->current_value;
    }

    public function getIsAchievedAttribute(): bool
    {
        return $this->current_value >= $this->target_value;
    }

    public function getPaceRequiredAttribute(): ?float
    {
        $period = $this->period;
        if (!$period) {
            return null;
        }

        $daysRemaining = $period->days_remaining;
        if ($daysRemaining <= 0) {
            return null;
        }

        $gap = $this->gap_to_target;
        if ($gap <= 0) {
            return 0;
        }

        return round($gap / $daysRemaining, 2);
    }

    public function getMetricLabelAttribute(): string
    {
        return match ($this->metric_type) {
            self::METRIC_REVENUE => 'Revenue',
            self::METRIC_DEALS => 'Closed Deals',
            self::METRIC_LEADS => 'New Leads',
            self::METRIC_CALLS => 'Calls',
            self::METRIC_MEETINGS => 'Meetings',
            self::METRIC_ACTIVITIES => 'Activities',
            self::METRIC_CUSTOM => $this->metric_field ?? 'Custom',
            default => $this->metric_type,
        };
    }

    // Methods
    public function recalculate(): void
    {
        if ($this->target_value > 0) {
            $this->attainment_percent = round(($this->current_value / $this->target_value) * 100, 2);
        } else {
            $this->attainment_percent = 0;
        }
        $this->save();
    }

    public function updateProgress(float $newValue): void
    {
        $this->current_value = $newValue;
        $this->recalculate();
    }

    public function addProgress(float $amount): void
    {
        $this->current_value += $amount;
        $this->recalculate();
    }

    public function createSnapshot(): QuotaSnapshot
    {
        return $this->snapshots()->create([
            'snapshot_date' => now()->toDateString(),
            'current_value' => $this->current_value,
            'attainment_percent' => $this->attainment_percent,
        ]);
    }

    public static function getMetricTypes(): array
    {
        return [
            self::METRIC_REVENUE => 'Revenue (Closed Won)',
            self::METRIC_DEALS => 'Closed Deals',
            self::METRIC_LEADS => 'New Leads',
            self::METRIC_CALLS => 'Calls Made',
            self::METRIC_MEETINGS => 'Meetings Held',
            self::METRIC_ACTIVITIES => 'Total Activities',
            self::METRIC_CUSTOM => 'Custom Metric',
        ];
    }
}
