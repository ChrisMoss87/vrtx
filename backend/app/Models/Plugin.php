<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Plugin catalog model (central database - not per-tenant)
 */
class Plugin extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'category',
        'tier',
        'pricing_model',
        'price_monthly',
        'price_yearly',
        'features',
        'requirements',
        'limits',
        'icon',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'requirements' => 'array',
        'limits' => 'array',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Categories
    public const CATEGORY_SALES = 'sales';
    public const CATEGORY_MARKETING = 'marketing';
    public const CATEGORY_COMMUNICATION = 'communication';
    public const CATEGORY_ANALYTICS = 'analytics';
    public const CATEGORY_AI = 'ai';
    public const CATEGORY_DOCUMENTS = 'documents';
    public const CATEGORY_SERVICE = 'service';
    public const CATEGORY_ADMIN = 'admin';

    // Tiers
    public const TIER_CORE = 'core';
    public const TIER_PROFESSIONAL = 'professional';
    public const TIER_ADVANCED = 'advanced';
    public const TIER_ENTERPRISE = 'enterprise';

    // Pricing models
    public const PRICING_PER_USER = 'per_user';
    public const PRICING_FLAT = 'flat';
    public const PRICING_USAGE = 'usage';
    public const PRICING_INCLUDED = 'included';

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }

    public function getYearlySavingsAttribute(): ?float
    {
        if (!$this->price_monthly || !$this->price_yearly) {
            return null;
        }

        $monthlyTotal = $this->price_monthly * 12;
        return round((($monthlyTotal - $this->price_yearly) / $monthlyTotal) * 100);
    }

    public function isIncluded(): bool
    {
        return $this->pricing_model === self::PRICING_INCLUDED;
    }

    public function isPerUser(): bool
    {
        return $this->pricing_model === self::PRICING_PER_USER;
    }

    public function isFlat(): bool
    {
        return $this->pricing_model === self::PRICING_FLAT;
    }

    public function isUsageBased(): bool
    {
        return $this->pricing_model === self::PRICING_USAGE;
    }
}
