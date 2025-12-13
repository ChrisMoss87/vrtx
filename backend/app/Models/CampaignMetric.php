<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CampaignMetric extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'campaign_id',
        'date',
        'sends',
        'delivered',
        'opens',
        'unique_opens',
        'clicks',
        'unique_clicks',
        'bounces',
        'unsubscribes',
        'conversions',
        'revenue',
    ];

    protected $casts = [
        'date' => 'date',
        'revenue' => 'decimal:2',
    ];

    protected $attributes = [
        'sends' => 0,
        'delivered' => 0,
        'opens' => 0,
        'unique_opens' => 0,
        'clicks' => 0,
        'unique_clicks' => 0,
        'bounces' => 0,
        'unsubscribes' => 0,
        'conversions' => 0,
        'revenue' => 0,
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function getOpenRateAttribute(): float
    {
        if ($this->delivered === 0) return 0;
        return round(($this->unique_opens / $this->delivered) * 100, 2);
    }

    public function getClickRateAttribute(): float
    {
        if ($this->delivered === 0) return 0;
        return round(($this->unique_clicks / $this->delivered) * 100, 2);
    }

    public function getBounceRateAttribute(): float
    {
        if ($this->sends === 0) return 0;
        return round(($this->bounces / $this->sends) * 100, 2);
    }

    public function getConversionRateAttribute(): float
    {
        if ($this->unique_clicks === 0) return 0;
        return round(($this->conversions / $this->unique_clicks) * 100, 2);
    }

    /**
     * Get or create metric record for a date
     */
    public static function getOrCreateForDate(int $campaignId, string $date): self
    {
        return self::firstOrCreate(
            ['campaign_id' => $campaignId, 'date' => $date],
            [
                'sends' => 0,
                'delivered' => 0,
                'opens' => 0,
                'unique_opens' => 0,
                'clicks' => 0,
                'unique_clicks' => 0,
                'bounces' => 0,
                'unsubscribes' => 0,
                'conversions' => 0,
                'revenue' => 0,
            ]
        );
    }

    /**
     * Increment a metric
     */
    public function incrementMetric(string $metric, int $amount = 1): void
    {
        if (in_array($metric, ['sends', 'delivered', 'opens', 'unique_opens', 'clicks', 'unique_clicks', 'bounces', 'unsubscribes', 'conversions'])) {
            $this->increment($metric, $amount);
        }
    }

    /**
     * Add revenue
     */
    public function addRevenue(float $amount): void
    {
        $this->increment('revenue', $amount);
    }
}
