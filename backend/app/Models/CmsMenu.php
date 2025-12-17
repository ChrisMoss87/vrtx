<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsMenu extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'location',
        'items',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'items' => 'array',
        'is_active' => 'boolean',
        'created_by' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
        'items' => '[]',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAtLocation($query, string $location)
    {
        return $query->where('location', $location);
    }

    public function getItemCount(): int
    {
        return count($this->items ?? []);
    }

    public function addItem(array $item): void
    {
        $items = $this->items ?? [];
        $items[] = $item;
        $this->update(['items' => $items]);
    }

    public function removeItem(int $index): void
    {
        $items = $this->items ?? [];
        if (isset($items[$index])) {
            unset($items[$index]);
            $this->update(['items' => array_values($items)]);
        }
    }

    public function updateItems(array $items): void
    {
        $this->update(['items' => $items]);
    }
}
