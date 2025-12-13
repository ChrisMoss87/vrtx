<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandingPageVariant extends Model
{
    protected $fillable = [
        'page_id',
        'name',
        'variant_code',
        'content',
        'styles',
        'traffic_percentage',
        'is_active',
        'is_winner',
        'declared_winner_at',
    ];

    protected $casts = [
        'content' => 'array',
        'styles' => 'array',
        'is_active' => 'boolean',
        'is_winner' => 'boolean',
        'declared_winner_at' => 'datetime',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'page_id');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(LandingPageAnalytics::class, 'variant_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(LandingPageVisit::class, 'variant_id');
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

    public function declareWinner(): void
    {
        // Mark all other variants as not winner
        $this->page->variants()
            ->where('id', '!=', $this->id)
            ->update([
                'is_winner' => false,
                'is_active' => false,
            ]);

        // Mark this one as winner
        $this->update([
            'is_winner' => true,
            'traffic_percentage' => 100,
            'declared_winner_at' => now(),
        ]);

        // Update main page content with winning variant
        $this->page->update([
            'content' => $this->content,
            'styles' => $this->styles,
            'is_ab_testing_enabled' => false,
        ]);
    }
}
