<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandingPageTemplate extends Model
{
    protected $fillable = [
        'name',
        'category',
        'description',
        'thumbnail_url',
        'content',
        'styles',
        'is_system',
        'is_active',
        'usage_count',
        'created_by',
    ];

    protected $casts = [
        'content' => 'array',
        'styles' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function getCategories(): array
    {
        return [
            'lead-capture' => 'Lead Capture',
            'event' => 'Event Registration',
            'webinar' => 'Webinar',
            'product' => 'Product Launch',
            'promo' => 'Promotion',
            'coming-soon' => 'Coming Soon',
            'thank-you' => 'Thank You',
            'general' => 'General',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(LandingPage::class, 'template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
