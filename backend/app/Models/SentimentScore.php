<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SentimentScore extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'record_module',
        'record_id',
        'score',
        'category',
        'emotion',
        'confidence',
        'details',
        'model_used',
        'analyzed_at',
    ];

    protected $casts = [
        'score' => 'decimal:3',
        'confidence' => 'decimal:3',
        'details' => 'array',
        'analyzed_at' => 'datetime',
    ];

    // Categories
    public const CATEGORY_POSITIVE = 'positive';
    public const CATEGORY_NEUTRAL = 'neutral';
    public const CATEGORY_NEGATIVE = 'negative';

    // Emotions
    public const EMOTION_HAPPY = 'happy';
    public const EMOTION_SATISFIED = 'satisfied';
    public const EMOTION_NEUTRAL = 'neutral';
    public const EMOTION_CONFUSED = 'confused';
    public const EMOTION_FRUSTRATED = 'frustrated';
    public const EMOTION_ANGRY = 'angry';
    public const EMOTION_URGENT = 'urgent';

    public function alerts(): HasMany
    {
        return $this->hasMany(SentimentAlert::class, 'sentiment_id');
    }

    /**
     * Convert numeric score to category
     */
    public static function scoreToCategory(float $score): string
    {
        return match (true) {
            $score >= 0.25 => self::CATEGORY_POSITIVE,
            $score <= -0.25 => self::CATEGORY_NEGATIVE,
            default => self::CATEGORY_NEUTRAL,
        };
    }

    /**
     * Check if sentiment is concerning
     */
    public function isConcerning(): bool
    {
        return $this->category === self::CATEGORY_NEGATIVE
            || in_array($this->emotion, [self::EMOTION_FRUSTRATED, self::EMOTION_ANGRY, self::EMOTION_URGENT]);
    }

    /**
     * Scope by category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope negative sentiments
     */
    public function scopeNegative($query)
    {
        return $query->where('category', self::CATEGORY_NEGATIVE);
    }

    /**
     * Scope by record
     */
    public function scopeForRecord($query, string $module, int $recordId)
    {
        return $query->where('record_module', $module)
            ->where('record_id', $recordId);
    }

    /**
     * Get display color for sentiment
     */
    public function getColorAttribute(): string
    {
        return match ($this->category) {
            self::CATEGORY_POSITIVE => 'green',
            self::CATEGORY_NEGATIVE => 'red',
            default => 'gray',
        };
    }

    /**
     * Get display icon for emotion
     */
    public function getIconAttribute(): string
    {
        return match ($this->emotion) {
            self::EMOTION_HAPPY, self::EMOTION_SATISFIED => 'smile',
            self::EMOTION_FRUSTRATED, self::EMOTION_ANGRY => 'frown',
            self::EMOTION_CONFUSED => 'help-circle',
            self::EMOTION_URGENT => 'alert-triangle',
            default => 'meh',
        };
    }
}
