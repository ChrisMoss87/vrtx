<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchHistory extends Model
{
    public $timestamps = false;

    protected $table = 'search_history';

    protected $fillable = [
        'user_id',
        'query',
        'type',
        'module_api_name',
        'filters',
        'results_count',
        'created_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a search.
     */
    public static function log(
        int $userId,
        string $query,
        int $resultsCount,
        string $type = 'global',
        ?string $moduleApiName = null,
        ?array $filters = null
    ): self {
        // Don't log very short queries
        if (strlen($query) < 2) {
            return new self();
        }

        return static::create([
            'user_id' => $userId,
            'query' => substr($query, 0, 255),
            'type' => $type,
            'module_api_name' => $moduleApiName,
            'filters' => $filters,
            'results_count' => $resultsCount,
            'created_at' => now(),
        ]);
    }

    /**
     * Get recent searches for a user.
     */
    public static function getRecent(int $userId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get unique recent searches for suggestions.
     */
    public static function getRecentUnique(int $userId, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', $userId)
            ->selectRaw('query, MAX(created_at) as last_searched, SUM(results_count) as total_results')
            ->groupBy('query')
            ->orderByDesc('last_searched')
            ->limit($limit)
            ->get();
    }

    /**
     * Get popular searches (for suggestions).
     */
    public static function getPopular(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::selectRaw('query, COUNT(*) as search_count, AVG(results_count) as avg_results')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('query')
            ->having('search_count', '>=', 2)
            ->orderByDesc('search_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Clear old search history.
     */
    public static function clearOld(int $daysToKeep = 30): int
    {
        return static::where('created_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }

    /**
     * Clear user's search history.
     */
    public static function clearForUser(int $userId): int
    {
        return static::where('user_id', $userId)->delete();
    }
}
