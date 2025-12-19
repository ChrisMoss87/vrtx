<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SentimentAggregate extends Model
{
    protected $fillable = [
        'record_module',
        'record_id',
        'average_score',
        'overall_sentiment',
        'positive_count',
        'neutral_count',
        'negative_count',
        'trend',
        'last_analyzed_at',
    ];

    protected $casts = [
        'average_score' => 'decimal:3',
        'trend' => 'decimal:3',
        'last_analyzed_at' => 'datetime',
    ];

    /**
     * Recalculate aggregate from sentiment scores
     */
    public function recalculate(): void
    {
        $scores = SentimentScore::forRecord($this->record_module, $this->record_id)->get();

        if ($scores->isEmpty()) {
            return;
        }

        $this->update([
            'average_score' => $scores->avg('score'),
            'overall_sentiment' => SentimentScore::scoreToCategory($scores->avg('score')),
            'positive_count' => $scores->where('category', SentimentScore::CATEGORY_POSITIVE)->count(),
            'neutral_count' => $scores->where('category', SentimentScore::CATEGORY_NEUTRAL)->count(),
            'negative_count' => $scores->where('category', SentimentScore::CATEGORY_NEGATIVE)->count(),
            'trend' => $this->calculateTrend($scores),
            'last_analyzed_at' => now(),
        ]);
    }

    /**
     * Calculate trend from recent scores
     */
    protected function calculateTrend($scores): float
    {
        $recentScores = $scores->where('analyzed_at', '>=', now()->subDays(7));
        $olderScores = $scores->where('analyzed_at', '<', now()->subDays(7));

        if ($recentScores->isEmpty() || $olderScores->isEmpty()) {
            return 0.0;
        }

        $recentAvg = $recentScores->avg('score');
        $olderAvg = $olderScores->avg('score');

        return round($recentAvg - $olderAvg, 3);
    }

    /**
     * Get or create aggregate for record
     */
    public static function getOrCreateFor(string $module, int $recordId): self
    {
        return self::firstOrCreate(
            ['record_module' => $module, 'record_id' => $recordId],
            ['overall_sentiment' => 'neutral']
        );
    }

    /**
     * Check if trend is declining
     */
    public function isDeclining(): bool
    {
        return $this->trend < -0.1;
    }

    /**
     * Check if trend is improving
     */
    public function isImproving(): bool
    {
        return $this->trend > 0.1;
    }
}
