<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Plugin usage tracking model (per-tenant database)
 */
class PluginUsage extends Model
{
    use HasFactory;

    protected $table = 'plugin_usage';

    protected $fillable = [
        'plugin_slug',
        'metric',
        'period_start',
        'period_end',
        'quantity',
        'limit_quantity',
        'overage_rate',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'overage_rate' => 'decimal:4',
    ];

    // Common metrics
    public const METRIC_API_CALLS = 'api_calls';
    public const METRIC_STORAGE_MB = 'storage_mb';
    public const METRIC_RECORDS = 'records';
    public const METRIC_EMAILS_SENT = 'emails_sent';
    public const METRIC_SMS_SENT = 'sms_sent';
    public const METRIC_AI_TOKENS = 'ai_tokens';
    public const METRIC_WORKFLOWS_RUN = 'workflows_run';

    public function scopeForPlugin($query, string $pluginSlug)
    {
        return $query->where('plugin_slug', $pluginSlug);
    }

    public function scopeForMetric($query, string $metric)
    {
        return $query->where('metric', $metric);
    }

    public function scopeCurrentPeriod($query)
    {
        return $query->where('period_start', '<=', now())
            ->where('period_end', '>=', now());
    }

    /**
     * Get remaining quantity before limit
     */
    public function getRemainingAttribute(): ?int
    {
        if ($this->limit_quantity === null) {
            return null; // Unlimited
        }

        return max(0, $this->limit_quantity - $this->quantity);
    }

    /**
     * Check if limit is reached
     */
    public function isLimitReached(): bool
    {
        if ($this->limit_quantity === null) {
            return false;
        }

        return $this->quantity >= $this->limit_quantity;
    }

    /**
     * Get usage percentage
     */
    public function getUsagePercentAttribute(): ?float
    {
        if ($this->limit_quantity === null || $this->limit_quantity === 0) {
            return null;
        }

        return min(100, round(($this->quantity / $this->limit_quantity) * 100, 1));
    }

    /**
     * Get overage quantity
     */
    public function getOverageAttribute(): int
    {
        if ($this->limit_quantity === null) {
            return 0;
        }

        return max(0, $this->quantity - $this->limit_quantity);
    }

    /**
     * Get overage cost
     */
    public function getOverageCostAttribute(): float
    {
        if (!$this->overage_rate) {
            return 0;
        }

        return $this->overage * $this->overage_rate;
    }

    /**
     * Increment usage
     */
    public function incrementUsage(int $amount = 1): void
    {
        $this->increment('quantity', $amount);
    }

    /**
     * Get or create current period usage
     */
    public static function getOrCreateForPeriod(
        string $pluginSlug,
        string $metric,
        ?int $limitQuantity = null,
        ?float $overageRate = null
    ): self {
        return static::firstOrCreate(
            [
                'plugin_slug' => $pluginSlug,
                'metric' => $metric,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
            ],
            [
                'quantity' => 0,
                'limit_quantity' => $limitQuantity,
                'overage_rate' => $overageRate,
            ]
        );
    }
}
