<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScoringModel extends Model
{
    protected $fillable = [
        'name',
        'description',
        'target_module',
        'status',
        'features',
        'weights',
        'model_type',
        'accuracy',
        'training_records',
        'trained_at',
        'is_default',
    ];

    protected $casts = [
        'features' => 'array',
        'weights' => 'array',
        'accuracy' => 'decimal:2',
        'trained_at' => 'datetime',
        'is_default' => 'boolean',
    ];

    // Statuses
    public const STATUS_DRAFT = 'draft';
    public const STATUS_TRAINING = 'training';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    // Model types
    public const TYPE_RULE_BASED = 'rule_based';
    public const TYPE_ML = 'ml';

    public function factors(): HasMany
    {
        return $this->hasMany(ScoringFactor::class, 'model_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(LeadScore::class, 'model_id');
    }

    /**
     * Scope active models
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Get the default model for a module
     */
    public static function getDefaultForModule(string $module): ?self
    {
        return self::where('target_module', $module)
            ->where('is_default', true)
            ->active()
            ->first();
    }

    /**
     * Set this model as default
     */
    public function setAsDefault(): void
    {
        // Remove default from other models for same module
        self::where('target_module', $this->target_module)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Calculate score for a record
     */
    public function calculateScore(array $recordData): array
    {
        $totalScore = 0;
        $maxPossible = 0;
        $breakdown = [];
        $explanations = [];

        foreach ($this->factors()->active()->get() as $factor) {
            $maxPossible += $factor->max_points;
            $points = $factor->evaluate($recordData);
            $totalScore += $points;

            $breakdown[$factor->name] = [
                'points' => $points,
                'max' => $factor->max_points,
                'category' => $factor->category,
            ];

            if ($points > 0) {
                $explanations[] = $factor->getExplanation($points);
            }
        }

        // Normalize to 0-100
        $normalizedScore = $maxPossible > 0
            ? (int) round(($totalScore / $maxPossible) * 100)
            : 0;

        return [
            'score' => $normalizedScore,
            'grade' => $this->scoreToGrade($normalizedScore),
            'breakdown' => $breakdown,
            'explanations' => $explanations,
        ];
    }

    /**
     * Convert score to letter grade
     */
    public function scoreToGrade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F',
        };
    }
}
