<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPageVisit extends Model
{
    use HasFactory;
    protected $fillable = [
        'page_id',
        'variant_id',
        'visitor_id',
        'session_id',
        'ip_address',
        'user_agent',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'device_type',
        'browser',
        'os',
        'country',
        'city',
        'converted',
        'converted_at',
        'submission_id',
        'time_on_page',
        'scroll_depth',
    ];

    protected $casts = [
        'converted' => 'boolean',
        'converted_at' => 'datetime',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'page_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(LandingPageVariant::class, 'variant_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(WebFormSubmission::class, 'submission_id');
    }

    public function markConverted(int $submissionId): void
    {
        $this->update([
            'converted' => true,
            'converted_at' => now(),
            'submission_id' => $submissionId,
        ]);

        // Also update analytics
        LandingPageAnalytics::recordConversion($this->page_id, $this->variant_id);
    }

    public function updateEngagement(int $timeOnPage, int $scrollDepth): void
    {
        $this->update([
            'time_on_page' => $timeOnPage,
            'scroll_depth' => $scrollDepth,
        ]);

        // Record bounce if time on page is less than 10 seconds
        if ($timeOnPage < 10) {
            LandingPageAnalytics::recordBounce($this->page_id, $this->variant_id);
        }
    }

    public static function parseUserAgent(string $userAgent): array
    {
        $deviceType = 'desktop';
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            $deviceType = preg_match('/iPad|Tablet/', $userAgent) ? 'tablet' : 'mobile';
        }

        $browser = 'Unknown';
        if (preg_match('/Chrome\/[\d.]+/', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox\/[\d.]+/', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari\/[\d.]+/', $userAgent) && !preg_match('/Chrome/', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge\/[\d.]+/', $userAgent)) {
            $browser = 'Edge';
        }

        $os = 'Unknown';
        if (preg_match('/Windows NT/', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS X/', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad/', $userAgent)) {
            $os = 'iOS';
        }

        return [
            'device_type' => $deviceType,
            'browser' => $browser,
            'os' => $os,
        ];
    }
}
