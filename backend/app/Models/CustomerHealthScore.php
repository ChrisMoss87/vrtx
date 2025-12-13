<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerHealthScore extends Model
{
    protected $fillable = [
        'related_module',
        'related_id',
        'overall_score',
        'engagement_score',
        'support_score',
        'product_usage_score',
        'payment_score',
        'relationship_score',
        'health_status',
        'score_breakdown',
        'risk_factors',
        'notes',
        'calculated_at',
    ];

    protected $casts = [
        'score_breakdown' => 'array',
        'risk_factors' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function history(): HasMany
    {
        return $this->hasMany(HealthScoreHistory::class)->orderBy('recorded_at', 'desc');
    }

    public function calculateHealthStatus(): string
    {
        if ($this->overall_score >= 70) {
            return 'healthy';
        } elseif ($this->overall_score >= 40) {
            return 'at_risk';
        }
        return 'critical';
    }

    public function calculateOverallScore(): int
    {
        // Weighted average of component scores
        $weights = [
            'engagement' => 0.25,
            'support' => 0.20,
            'product_usage' => 0.25,
            'payment' => 0.15,
            'relationship' => 0.15,
        ];

        $total = 0;
        $total += $this->engagement_score * $weights['engagement'];
        $total += $this->support_score * $weights['support'];
        $total += $this->product_usage_score * $weights['product_usage'];
        $total += $this->payment_score * $weights['payment'];
        $total += $this->relationship_score * $weights['relationship'];

        return (int) round($total);
    }

    public function recordHistory(): void
    {
        $this->history()->create([
            'overall_score' => $this->overall_score,
            'scores_snapshot' => [
                'engagement' => $this->engagement_score,
                'support' => $this->support_score,
                'product_usage' => $this->product_usage_score,
                'payment' => $this->payment_score,
                'relationship' => $this->relationship_score,
            ],
            'recorded_at' => now(),
        ]);
    }

    public function scopeHealthy($query)
    {
        return $query->where('health_status', 'healthy');
    }

    public function scopeAtRisk($query)
    {
        return $query->where('health_status', 'at_risk');
    }

    public function scopeCritical($query)
    {
        return $query->where('health_status', 'critical');
    }

    public function scopeForModule($query, string $module, int $recordId)
    {
        return $query->where('related_module', $module)->where('related_id', $recordId);
    }
}
