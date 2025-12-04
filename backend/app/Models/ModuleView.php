<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleView extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'user_id',
        'name',
        'description',
        'filters',
        'sorting',
        'column_visibility',
        'column_order',
        'column_widths',
        'page_size',
        'is_default',
        'is_shared',
        'display_order',
    ];

    protected $casts = [
        'filters' => 'array',
        'sorting' => 'array',
        'column_visibility' => 'array',
        'column_order' => 'array',
        'column_widths' => 'array',
        'page_size' => 'integer',
        'is_default' => 'boolean',
        'is_shared' => 'boolean',
        'display_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'filters' => '[]',
        'sorting' => '[]',
        'column_visibility' => '{}',
        'page_size' => 50,
        'is_default' => false,
        'is_shared' => false,
        'display_order' => 0,
    ];

    /**
     * Get the module that owns the view.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user that owns the view.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include default views.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to only include shared views.
     */
    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    /**
     * Scope a query to include views accessible by a user.
     */
    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_shared', true);
        });
    }

    /**
     * Scope a query to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
