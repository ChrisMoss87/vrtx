<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalContentBlock extends Model
{
    public const CATEGORY_ABOUT_US = 'about_us';
    public const CATEGORY_CASE_STUDIES = 'case_studies';
    public const CATEGORY_TEAM_BIOS = 'team_bios';
    public const CATEGORY_TERMS = 'terms';
    public const CATEGORY_TESTIMONIALS = 'testimonials';
    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_ABOUT_US,
        self::CATEGORY_CASE_STUDIES,
        self::CATEGORY_TEAM_BIOS,
        self::CATEGORY_TERMS,
        self::CATEGORY_TESTIMONIALS,
        self::CATEGORY_OTHER,
    ];

    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_PRICING = 'pricing';
    public const TYPE_TEAM = 'team';
    public const TYPE_TESTIMONIAL = 'testimonial';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_IMAGE,
        self::TYPE_PRICING,
        self::TYPE_TEAM,
        self::TYPE_TESTIMONIAL,
    ];

    protected $fillable = [
        'name',
        'category',
        'block_type',
        'content',
        'settings',
        'thumbnail_url',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    // Relationships
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('block_type', $type);
    }
}
