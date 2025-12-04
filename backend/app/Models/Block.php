<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Block extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'name',
        'type',
        'display_order',
        'settings',
    ];

    protected $casts = [
        'module_id' => 'integer',
        'display_order' => 'integer',
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'type' => 'section',
        'display_order' => 0,
        'settings' => '{}',
    ];

    /**
     * Get the module that owns the block.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the fields in this block.
     */
    public function fields(): HasMany
    {
        return $this->hasMany(Field::class)->orderBy('display_order');
    }

    /**
     * Scope a query to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }
}
