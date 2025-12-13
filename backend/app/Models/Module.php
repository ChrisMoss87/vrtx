<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'singular_name',
        'api_name',
        'icon',
        'description',
        'is_active',
        'settings',
        'default_filters',
        'default_sorting',
        'default_column_visibility',
        'default_page_size',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'default_filters' => 'array',
        'default_sorting' => 'array',
        'default_column_visibility' => 'array',
        'default_page_size' => 'integer',
        'display_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'settings' => '{}',
        'default_page_size' => 50,
        'display_order' => 0,
    ];

    /**
     * Get the blocks for the module.
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)->orderBy('display_order');
    }

    /**
     * Get the fields for the module.
     */
    public function fields(): HasMany
    {
        return $this->hasMany(Field::class)->orderBy('display_order');
    }

    /**
     * Get the records for the module.
     */
    public function records(): HasMany
    {
        return $this->hasMany(ModuleRecord::class);
    }

    /**
     * Get the views for the module.
     */
    public function views(): HasMany
    {
        return $this->hasMany(ModuleView::class)->ordered();
    }

    /**
     * Scope a query to only include active modules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    /**
     * Scope a query to filter by API name.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $apiName
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByApiName($query, string $apiName)
    {
        return $query->where('api_name', $apiName);
    }

    /**
     * Find a module by its API name.
     */
    public static function findByApiName(string $apiName): ?self
    {
        return static::byApiName($apiName)->first();
    }

    /**
     * Find a module by its API name or throw 404.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findByApiNameOrFail(string $apiName): self
    {
        return static::byApiName($apiName)->firstOrFail();
    }

    /**
     * Get module with all relationships needed for display.
     * Uses efficient eager loading.
     */
    public static function findByApiNameWithDetails(string $apiName): ?self
    {
        return static::byApiName($apiName)
            ->with([
                'blocks' => fn ($q) => $q->orderBy('display_order'),
                'blocks.fields' => fn ($q) => $q->orderBy('display_order'),
                'blocks.fields.options' => fn ($q) => $q->orderBy('display_order'),
            ])
            ->first();
    }

    /**
     * Get module with all relationships needed for display or throw 404.
     * Uses efficient eager loading.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findByApiNameWithDetailsOrFail(string $apiName): self
    {
        return static::byApiName($apiName)
            ->with([
                'blocks' => fn ($q) => $q->orderBy('display_order'),
                'blocks.fields' => fn ($q) => $q->orderBy('display_order'),
                'blocks.fields.options' => fn ($q) => $q->orderBy('display_order'),
            ])
            ->firstOrFail();
    }
}
