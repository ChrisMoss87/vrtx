<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebFormAnalytics extends Model
{
    use HasFactory;

    protected $table = 'web_form_analytics';

    protected $fillable = [
        'web_form_id',
        'date',
        'views',
        'submissions',
        'successful_submissions',
        'spam_blocked',
    ];

    protected $casts = [
        'date' => 'date',
        'views' => 'integer',
        'submissions' => 'integer',
        'successful_submissions' => 'integer',
        'spam_blocked' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'views' => 0,
        'submissions' => 0,
        'successful_submissions' => 0,
        'spam_blocked' => 0,
    ];

    /**
     * Get the form this analytics belongs to.
     */
    public function webForm(): BelongsTo
    {
        return $this->belongsTo(WebForm::class);
    }

    /**
     * Get conversion rate percentage.
     */
    public function getConversionRateAttribute(): float
    {
        if ($this->views === 0) {
            return 0.0;
        }

        return round(($this->successful_submissions / $this->views) * 100, 2);
    }

    /**
     * Get or create analytics record for a form and date.
     */
    public static function getOrCreateForDate(int $formId, ?string $date = null): self
    {
        $date = $date ?? now()->toDateString();

        return static::firstOrCreate([
            'web_form_id' => $formId,
            'date' => $date,
        ]);
    }

    /**
     * Increment view count.
     */
    public static function incrementViews(int $formId): void
    {
        $analytics = static::getOrCreateForDate($formId);
        $analytics->increment('views');
    }

    /**
     * Increment submission count.
     */
    public static function incrementSubmissions(int $formId, bool $successful = true): void
    {
        $analytics = static::getOrCreateForDate($formId);
        $analytics->increment('submissions');

        if ($successful) {
            $analytics->increment('successful_submissions');
        }
    }

    /**
     * Increment spam blocked count.
     */
    public static function incrementSpamBlocked(int $formId): void
    {
        $analytics = static::getOrCreateForDate($formId);
        $analytics->increment('spam_blocked');
    }

    /**
     * Get analytics summary for a form over a date range.
     */
    public static function getSummary(int $formId, string $startDate, string $endDate): array
    {
        $analytics = static::where('web_form_id', $formId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalViews = $analytics->sum('views');
        $totalSubmissions = $analytics->sum('submissions');
        $totalSuccessful = $analytics->sum('successful_submissions');
        $totalSpam = $analytics->sum('spam_blocked');

        return [
            'total_views' => $totalViews,
            'total_submissions' => $totalSubmissions,
            'successful_submissions' => $totalSuccessful,
            'spam_blocked' => $totalSpam,
            'conversion_rate' => $totalViews > 0
                ? round(($totalSuccessful / $totalViews) * 100, 2)
                : 0,
            'daily' => $analytics->map(fn ($a) => [
                'date' => $a->date->toDateString(),
                'views' => $a->views,
                'submissions' => $a->submissions,
                'successful' => $a->successful_submissions,
                'spam' => $a->spam_blocked,
                'conversion_rate' => $a->conversion_rate,
            ])->toArray(),
        ];
    }
}
