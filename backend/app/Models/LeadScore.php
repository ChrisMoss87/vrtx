<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadScore extends Model
{
    protected $fillable = [
        'model_id',
        'record_module',
        'record_id',
        'score',
        'grade',
        'factor_breakdown',
        'explanation',
        'conversion_probability',
        'calculated_at',
    ];

    protected $casts = [
        'factor_breakdown' => 'array',
        'explanation' => 'array',
        'conversion_probability' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function scoringModel(): BelongsTo
    {
        return $this->belongsTo(ScoringModel::class, 'model_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(LeadScoreHistory::class, 'lead_score_id')->orderBy('created_at', 'desc');
    }

    /**
     * Get the CRM record
     */
    public function getRecord(): ?ModuleRecord
    {
        return ModuleRecord::forModule($this->record_module)
            ->find($this->record_id);
    }

    /**
     * Update score and track history
     */
    public function updateScore(int $newScore, string $newGrade, array $breakdown, array $explanations, ?string $reason = null): void
    {
        $oldScore = $this->score;

        // Record history if score changed
        if ($oldScore !== $newScore) {
            LeadScoreHistory::create([
                'lead_score_id' => $this->id,
                'score' => $newScore,
                'grade' => $newGrade,
                'change_reason' => $reason ?? 'Score recalculated',
            ]);
        }

        $this->update([
            'score' => $newScore,
            'grade' => $newGrade,
            'factor_breakdown' => $breakdown,
            'explanation' => $explanations,
            'calculated_at' => now(),
        ]);
    }

    /**
     * Get score trend (last N days)
     */
    public function getTrend(int $days = 30): array
    {
        return $this->history()
            ->where('created_at', '>=', now()->subDays($days))
            ->get(['score', 'created_at'])
            ->map(fn($h) => [
                'score' => $h->score,
                'date' => $h->created_at->format('Y-m-d'),
            ])
            ->toArray();
    }

    /**
     * Scope by grade
     */
    public function scopeGrade($query, string $grade)
    {
        return $query->where('grade', $grade);
    }

    /**
     * Scope high scores (A or B)
     */
    public function scopeHighScores($query)
    {
        return $query->whereIn('grade', ['A', 'B']);
    }

    /**
     * Scope low scores (D or F)
     */
    public function scopeLowScores($query)
    {
        return $query->whereIn('grade', ['D', 'F']);
    }
}
