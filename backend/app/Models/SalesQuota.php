<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesQuota extends Model
{
    use HasFactory;

    public const PERIOD_MONTH = 'month';
    public const PERIOD_QUARTER = 'quarter';
    public const PERIOD_YEAR = 'year';

    protected $fillable = [
        'user_id',
        'pipeline_id',
        'team_id',
        'period_type',
        'period_start',
        'period_end',
        'quota_amount',
        'currency',
        'notes',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'pipeline_id' => 'integer',
        'team_id' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'quota_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'currency' => 'USD',
    ];

    /**
     * Get the user this quota belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pipeline this quota applies to.
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * Scope to get quotas for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get quotas for a specific period.
     */
    public function scopeForPeriod($query, string $periodType, \DateTimeInterface $periodStart)
    {
        return $query->where('period_type', $periodType)
            ->where('period_start', '<=', $periodStart)
            ->where('period_end', '>=', $periodStart);
    }

    /**
     * Scope to get current period quotas.
     */
    public function scopeCurrent($query)
    {
        $now = now();
        return $query->where('period_start', '<=', $now)
            ->where('period_end', '>=', $now);
    }

    /**
     * Scope to get team quotas.
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Check if this quota is for the current period.
     */
    public function isCurrentPeriod(): bool
    {
        $now = now();
        return $this->period_start <= $now && $this->period_end >= $now;
    }

    /**
     * Get quota attainment percentage.
     */
    public function getAttainment(float $actualAmount): float
    {
        if ($this->quota_amount <= 0) {
            return 0;
        }

        return round(($actualAmount / $this->quota_amount) * 100, 1);
    }

    /**
     * Get remaining amount to hit quota.
     */
    public function getRemainingAmount(float $actualAmount): float
    {
        return max(0, (float) $this->quota_amount - $actualAmount);
    }

    /**
     * Check if quota has been achieved.
     */
    public function isAchieved(float $actualAmount): bool
    {
        return $actualAmount >= $this->quota_amount;
    }
}
