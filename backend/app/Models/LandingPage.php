<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandingPage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'template_id',
        'content',
        'settings',
        'seo_settings',
        'styles',
        'custom_domain',
        'custom_domain_verified',
        'favicon_url',
        'og_image_url',
        'web_form_id',
        'thank_you_page_type',
        'thank_you_message',
        'thank_you_redirect_url',
        'thank_you_page_id',
        'is_ab_testing_enabled',
        'campaign_id',
        'created_by',
        'published_at',
    ];

    protected $casts = [
        'content' => 'array',
        'settings' => 'array',
        'seo_settings' => 'array',
        'styles' => 'array',
        'custom_domain_verified' => 'boolean',
        'is_ab_testing_enabled' => 'boolean',
        'published_at' => 'datetime',
    ];

    public static function getStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived',
        ];
    }

    public static function getThankYouTypes(): array
    {
        return [
            'message' => 'Show Message',
            'redirect' => 'Redirect to URL',
            'page' => 'Show Another Page',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LandingPageTemplate::class, 'template_id');
    }

    public function webForm(): BelongsTo
    {
        return $this->belongsTo(WebForm::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function thankYouPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'thank_you_page_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(LandingPageVariant::class, 'page_id');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(LandingPageAnalytics::class, 'page_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(LandingPageVisit::class, 'page_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function unpublish(): void
    {
        $this->update([
            'status' => 'draft',
        ]);
    }

    public function archive(): void
    {
        $this->update([
            'status' => 'archived',
        ]);
    }

    public function getPublicUrl(): string
    {
        if ($this->custom_domain && $this->custom_domain_verified) {
            return "https://{$this->custom_domain}";
        }

        return config('app.url') . "/p/{$this->slug}";
    }

    public function getTotalViews(): int
    {
        return $this->analytics()->sum('views');
    }

    public function getTotalConversions(): int
    {
        return $this->analytics()->sum('form_submissions');
    }

    public function getConversionRate(): float
    {
        $views = $this->getTotalViews();

        if ($views === 0) {
            return 0;
        }

        return round(($this->getTotalConversions() / $views) * 100, 2);
    }

    public function selectVariant(?string $visitorId = null): ?LandingPageVariant
    {
        if (!$this->is_ab_testing_enabled) {
            return null;
        }

        $activeVariants = $this->variants()
            ->where('is_active', true)
            ->orderBy('variant_code')
            ->get();

        if ($activeVariants->isEmpty()) {
            return null;
        }

        // If there's a visitor ID, use consistent hashing for same variant
        if ($visitorId) {
            $hash = crc32($visitorId . $this->id);
            $percentage = $hash % 100;
        } else {
            $percentage = rand(0, 99);
        }

        $cumulative = 0;
        foreach ($activeVariants as $variant) {
            $cumulative += $variant->traffic_percentage;
            if ($percentage < $cumulative) {
                return $variant;
            }
        }

        return $activeVariants->last();
    }
}
