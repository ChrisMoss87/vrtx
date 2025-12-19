<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    protected $fillable = [
        'feature',
        'model',
        'input_tokens',
        'output_tokens',
        'cost_cents',
        'user_id',
        'entity_type',
        'entity_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Features
    public const FEATURE_EMAIL_COMPOSE = 'email_compose';
    public const FEATURE_EMAIL_IMPROVE = 'email_improve';
    public const FEATURE_EMAIL_REPLY = 'email_reply';
    public const FEATURE_SUBJECT_SUGGEST = 'subject_suggest';
    public const FEATURE_LEAD_SCORING = 'lead_scoring';
    public const FEATURE_SENTIMENT = 'sentiment';
    public const FEATURE_MEETING_SUMMARY = 'meeting_summary';
    public const FEATURE_PREDICTION = 'prediction';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get total tokens used
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->input_tokens + $this->output_tokens;
    }

    /**
     * Scope by feature
     */
    public function scopeForFeature($query, string $feature)
    {
        return $query->where('feature', $feature);
    }

    /**
     * Scope for current month
     */
    public function scopeCurrentMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    /**
     * Get usage summary for period
     */
    public static function getSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $query = self::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return [
            'total_requests' => $query->count(),
            'total_input_tokens' => $query->sum('input_tokens'),
            'total_output_tokens' => $query->sum('output_tokens'),
            'total_cost_cents' => $query->sum('cost_cents'),
            'by_feature' => $query->clone()
                ->selectRaw('feature, COUNT(*) as count, SUM(cost_cents) as cost')
                ->groupBy('feature')
                ->pluck('cost', 'feature')
                ->toArray(),
        ];
    }
}
