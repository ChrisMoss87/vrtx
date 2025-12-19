<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPageAnalytics extends Model
{
    protected $table = 'landing_page_analytics';

    protected $fillable = [
        'page_id',
        'variant_id',
        'date',
        'views',
        'unique_visitors',
        'form_submissions',
        'bounces',
        'avg_time_on_page',
        'referrer_breakdown',
        'device_breakdown',
        'location_breakdown',
    ];

    protected $casts = [
        'date' => 'date',
        'referrer_breakdown' => 'array',
        'device_breakdown' => 'array',
        'location_breakdown' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'page_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(LandingPageVariant::class, 'variant_id');
    }

    public function getBounceRate(): float
    {
        if ($this->views === 0) {
            return 0;
        }

        return round(($this->bounces / $this->views) * 100, 2);
    }

    public function getConversionRate(): float
    {
        if ($this->views === 0) {
            return 0;
        }

        return round(($this->form_submissions / $this->views) * 100, 2);
    }

    public static function recordView(
        int $pageId,
        ?int $variantId = null,
        array $metadata = []
    ): self {
        $date = now()->toDateString();

        $analytics = self::firstOrCreate(
            [
                'page_id' => $pageId,
                'variant_id' => $variantId,
                'date' => $date,
            ],
            [
                'views' => 0,
                'unique_visitors' => 0,
                'form_submissions' => 0,
                'bounces' => 0,
                'referrer_breakdown' => [],
                'device_breakdown' => [],
                'location_breakdown' => [],
            ]
        );

        $analytics->increment('views');

        // Update breakdowns
        if (!empty($metadata['referrer'])) {
            $breakdown = $analytics->referrer_breakdown ?? [];
            $referrer = parse_url($metadata['referrer'], PHP_URL_HOST) ?? 'direct';
            $breakdown[$referrer] = ($breakdown[$referrer] ?? 0) + 1;
            $analytics->referrer_breakdown = $breakdown;
        }

        if (!empty($metadata['device_type'])) {
            $breakdown = $analytics->device_breakdown ?? [];
            $breakdown[$metadata['device_type']] = ($breakdown[$metadata['device_type']] ?? 0) + 1;
            $analytics->device_breakdown = $breakdown;
        }

        if (!empty($metadata['country'])) {
            $breakdown = $analytics->location_breakdown ?? [];
            $breakdown[$metadata['country']] = ($breakdown[$metadata['country']] ?? 0) + 1;
            $analytics->location_breakdown = $breakdown;
        }

        $analytics->save();

        return $analytics;
    }

    public static function recordConversion(int $pageId, ?int $variantId = null): void
    {
        $date = now()->toDateString();

        self::where('page_id', $pageId)
            ->where('variant_id', $variantId)
            ->where('date', $date)
            ->increment('form_submissions');
    }

    public static function recordBounce(int $pageId, ?int $variantId = null): void
    {
        $date = now()->toDateString();

        self::where('page_id', $pageId)
            ->where('variant_id', $variantId)
            ->where('date', $date)
            ->increment('bounces');
    }
}
