<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoringFactor extends Model
{
    protected $fillable = [
        'model_id',
        'name',
        'category',
        'factor_type',
        'config',
        'weight',
        'max_points',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
    ];

    // Categories
    public const CATEGORY_DEMOGRAPHIC = 'demographic';
    public const CATEGORY_BEHAVIORAL = 'behavioral';
    public const CATEGORY_ENGAGEMENT = 'engagement';
    public const CATEGORY_FIRMOGRAPHIC = 'firmographic';

    // Factor types
    public const TYPE_FIELD_VALUE = 'field_value';
    public const TYPE_FIELD_FILLED = 'field_filled';
    public const TYPE_ACTIVITY_COUNT = 'activity_count';
    public const TYPE_RECENCY = 'recency';
    public const TYPE_CUSTOM = 'custom';

    public function model(): BelongsTo
    {
        return $this->belongsTo(ScoringModel::class, 'model_id');
    }

    /**
     * Scope active factors
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('display_order');
    }

    /**
     * Evaluate this factor against record data
     */
    public function evaluate(array $recordData): int
    {
        return match ($this->factor_type) {
            self::TYPE_FIELD_VALUE => $this->evaluateFieldValue($recordData),
            self::TYPE_FIELD_FILLED => $this->evaluateFieldFilled($recordData),
            self::TYPE_ACTIVITY_COUNT => $this->evaluateActivityCount($recordData),
            self::TYPE_RECENCY => $this->evaluateRecency($recordData),
            default => 0,
        };
    }

    /**
     * Evaluate field value matching
     */
    protected function evaluateFieldValue(array $recordData): int
    {
        $field = $this->config['field'] ?? null;
        $values = $this->config['values'] ?? [];
        $pointsPerMatch = $this->config['points_per_match'] ?? $this->max_points;

        if (!$field || !isset($recordData[$field])) {
            return 0;
        }

        $actualValue = $recordData[$field];

        // Check if value matches any of the target values
        foreach ($values as $valueConfig) {
            $targetValue = $valueConfig['value'] ?? null;
            $points = $valueConfig['points'] ?? $pointsPerMatch;

            if ($actualValue == $targetValue) {
                return min($points, $this->max_points);
            }
        }

        return 0;
    }

    /**
     * Evaluate if field has a value
     */
    protected function evaluateFieldFilled(array $recordData): int
    {
        $field = $this->config['field'] ?? null;

        if (!$field) {
            return 0;
        }

        $value = $recordData[$field] ?? null;

        if ($value !== null && $value !== '' && $value !== []) {
            return $this->max_points;
        }

        return 0;
    }

    /**
     * Evaluate activity count
     */
    protected function evaluateActivityCount(array $recordData): int
    {
        $activityType = $this->config['activity_type'] ?? null;
        $countField = $this->config['count_field'] ?? 'activity_count';
        $thresholds = $this->config['thresholds'] ?? [];

        $count = $recordData[$countField] ?? 0;

        // Find matching threshold
        $points = 0;
        foreach ($thresholds as $threshold) {
            if ($count >= ($threshold['min'] ?? 0)) {
                $points = $threshold['points'] ?? 0;
            }
        }

        return min($points, $this->max_points);
    }

    /**
     * Evaluate recency
     */
    protected function evaluateRecency(array $recordData): int
    {
        $dateField = $this->config['date_field'] ?? null;
        $thresholds = $this->config['thresholds'] ?? [];

        if (!$dateField || !isset($recordData[$dateField])) {
            return 0;
        }

        $date = $recordData[$dateField];
        $daysSince = now()->diffInDays($date);

        // Find matching threshold (thresholds should be sorted ascending)
        $points = 0;
        foreach ($thresholds as $threshold) {
            if ($daysSince <= ($threshold['days'] ?? PHP_INT_MAX)) {
                $points = $threshold['points'] ?? 0;
                break;
            }
        }

        return min($points, $this->max_points);
    }

    /**
     * Get human-readable explanation for points earned
     */
    public function getExplanation(int $points): string
    {
        $percentage = $this->max_points > 0
            ? round(($points / $this->max_points) * 100)
            : 0;

        return sprintf(
            '%s: +%d points (%d%% of %d max)',
            $this->name,
            $points,
            $percentage,
            $this->max_points
        );
    }
}
