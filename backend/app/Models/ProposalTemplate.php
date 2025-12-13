<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProposalTemplate extends Model
{
    use HasFactory;

    public const CATEGORY_SALES = 'sales';
    public const CATEGORY_SERVICES = 'services';
    public const CATEGORY_PARTNERSHIP = 'partnership';
    public const CATEGORY_CONSULTING = 'consulting';
    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_SALES,
        self::CATEGORY_SERVICES,
        self::CATEGORY_PARTNERSHIP,
        self::CATEGORY_CONSULTING,
        self::CATEGORY_OTHER,
    ];

    protected $fillable = [
        'name',
        'description',
        'category',
        'default_sections',
        'styling',
        'cover_image_url',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'default_sections' => 'array',
        'styling' => 'array',
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

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'template_id');
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
}
