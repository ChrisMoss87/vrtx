<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitorObjection extends Model
{
    protected $fillable = [
        'competitor_id',
        'objection',
        'counter_script',
        'effectiveness_score',
        'use_count',
        'success_count',
        'created_by',
    ];

    protected $casts = [
        'effectiveness_score' => 'decimal:2',
        'use_count' => 'integer',
        'success_count' => 'integer',
    ];

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(ObjectionFeedback::class, 'objection_id');
    }

    public function recordUsage(bool $wasSuccessful): void
    {
        $this->increment('use_count');

        if ($wasSuccessful) {
            $this->increment('success_count');
        }

        $this->recalculateEffectiveness();
    }

    public function recalculateEffectiveness(): void
    {
        if ($this->use_count === 0) {
            $this->update(['effectiveness_score' => null]);
            return;
        }

        $score = round(($this->success_count / $this->use_count) * 100, 2);
        $this->update(['effectiveness_score' => $score]);
    }

    public function getEffectivenessLabel(): string
    {
        if ($this->effectiveness_score === null || $this->use_count < 3) {
            return 'Not enough data';
        }

        if ($this->effectiveness_score >= 70) {
            return 'Highly effective';
        }

        if ($this->effectiveness_score >= 50) {
            return 'Moderately effective';
        }

        return 'Needs improvement';
    }
}
