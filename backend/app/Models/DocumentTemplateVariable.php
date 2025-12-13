<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplateVariable extends Model
{
    public const CATEGORY_CONTACT = 'contact';
    public const CATEGORY_COMPANY = 'company';
    public const CATEGORY_DEAL = 'deal';
    public const CATEGORY_USER = 'user';
    public const CATEGORY_CUSTOM = 'custom';
    public const CATEGORY_SYSTEM = 'system';

    public const CATEGORIES = [
        self::CATEGORY_CONTACT,
        self::CATEGORY_COMPANY,
        self::CATEGORY_DEAL,
        self::CATEGORY_USER,
        self::CATEGORY_CUSTOM,
        self::CATEGORY_SYSTEM,
    ];

    protected $fillable = [
        'name',
        'api_name',
        'category',
        'field_path',
        'default_value',
        'format',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    // Scopes
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    // Helper to get grouped variables
    public static function getGroupedVariables(): array
    {
        return static::all()
            ->groupBy('category')
            ->toArray();
    }
}
