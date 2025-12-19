<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearch extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'query',
        'type',
        'module_api_name',
        'filters',
        'is_pinned',
        'use_count',
        'last_used_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_pinned' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record usage of this saved search.
     */
    public function recordUsage(): void
    {
        $this->update([
            'use_count' => $this->use_count + 1,
            'last_used_at' => now(),
        ]);
    }

    /**
     * Get user's saved searches.
     */
    public static function getForUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', $userId)
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_used_at')
            ->get();
    }

    /**
     * Get user's pinned searches.
     */
    public static function getPinnedForUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', $userId)
            ->where('is_pinned', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Toggle pinned status.
     */
    public function togglePin(): void
    {
        $this->update(['is_pinned' => !$this->is_pinned]);
    }
}
