<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastSnapshot extends Model
{
    use HasFactory;

    public const PERIOD_WEEK = 'week';
    public const PERIOD_MONTH = 'month';
    public const PERIOD_QUARTER = 'quarter';
    public const PERIOD_YEAR = 'year';

    protected $fillable = [
        'user_id',
        'pipeline_id',
        'period_type',
        'period_start',
        'period_end',
        'commit_amount',
        'best_case_amount',
        'pipeline_amount',
        'weighted_amount',
        'closed_won_amount',
        'deal_count',
        'snapshot_date',
        'metadata',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'pipeline_id' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'commit_amount' => 'decimal:2',
        'best_case_amount' => 'decimal:2',
        'pipeline_amount' => 'decimal:2',
        'weighted_amount' => 'decimal:2',
        'closed_won_amount' => 'decimal:2',
        'deal_count' => 'integer',
        'snapshot_date' => 'date',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'commit_amount' => 0,
        'best_case_amount' => 0,
        'pipeline_amount' => 0,
        'weighted_amount' => 0,
        'closed_won_amount' => 0,
        'deal_count' => 0,
    ];

    /**
     * Get the user this snapshot belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pipeline this snapshot belongs to.
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * Scope to get snapshots for a specific period.
     */
    public function scopeForPeriod($query, string $periodType, \DateTimeInterface $periodStart)
    {
        return $query->where('period_type', $periodType)
            ->where('period_start', $periodStart);
    }

    /**
     * Scope to get snapshots for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get snapshots for a specific pipeline.
     */
    public function scopeForPipeline($query, int $pipelineId)
    {
        return $query->where('pipeline_id', $pipelineId);
    }

    /**
     * Scope to get the latest snapshot for each period.
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('snapshot_date', 'desc');
    }

    /**
     * Get total forecast amount (commit + best case).
     */
    public function getTotalForecastAttribute(): float
    {
        return (float) $this->commit_amount + (float) $this->best_case_amount;
    }

    /**
     * Get forecast accuracy if closed won is available.
     */
    public function getAccuracyAttribute(): ?float
    {
        if ($this->weighted_amount <= 0) {
            return null;
        }

        return round(($this->closed_won_amount / $this->weighted_amount) * 100, 1);
    }
}
