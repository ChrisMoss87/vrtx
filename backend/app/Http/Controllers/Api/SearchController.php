<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\SavedSearch;
use App\Models\SearchHistory;
use App\Models\SearchIndex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    /**
     * Global search across all modules.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $modules = $request->input('modules'); // Optional: filter to specific modules
        $limit = $request->integer('limit', 20);

        if (strlen($query) < 2) {
            return response()->json([
                'results' => [],
                'total' => 0,
            ]);
        }

        $moduleApiNames = null;
        if ($modules) {
            $moduleApiNames = is_array($modules) ? $modules : explode(',', $modules);
        }

        // Use simple search for better partial matching
        $results = SearchIndex::simpleSearch($query, $moduleApiNames, $limit);

        // Group results by module
        $groupedResults = [];
        foreach ($results as $result) {
            $moduleApiName = $result->module_api_name;
            if (!isset($groupedResults[$moduleApiName])) {
                $module = Module::where('api_name', $moduleApiName)->first();
                $groupedResults[$moduleApiName] = [
                    'module' => [
                        'api_name' => $moduleApiName,
                        'name' => $module->name ?? $moduleApiName,
                        'icon' => $module->icon ?? 'file',
                    ],
                    'results' => [],
                ];
            }

            $groupedResults[$moduleApiName]['results'][] = [
                'id' => $result->record_id,
                'primary_value' => $result->primary_value,
                'secondary_value' => $result->secondary_value,
                'metadata' => $result->metadata,
            ];
        }

        // Log the search
        SearchHistory::log(
            Auth::id(),
            $query,
            $results->count(),
            'global',
            null,
            $moduleApiNames ? ['modules' => $moduleApiNames] : null
        );

        return response()->json([
            'results' => array_values($groupedResults),
            'total' => $results->count(),
            'query' => $query,
        ]);
    }

    /**
     * Quick search with instant results (for as-you-type).
     */
    public function quickSearch(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $limit = $request->integer('limit', 8);

        if (strlen($query) < 2) {
            // Return recent searches instead
            $recentSearches = SearchHistory::getRecentUnique(Auth::id(), 5);

            return response()->json([
                'results' => [],
                'suggestions' => $recentSearches->pluck('query')->toArray(),
                'type' => 'recent',
            ]);
        }

        $searchTerm = '%' . strtolower($query) . '%';

        // Fast search on primary value only
        $results = SearchIndex::query()
            ->whereRaw('LOWER(primary_value) LIKE ?', [$searchTerm])
            ->with(['module:id,name,api_name,icon'])
            ->orderBy('primary_value')
            ->limit($limit)
            ->get();

        $formattedResults = $results->map(function ($result) {
            return [
                'id' => $result->record_id,
                'module_api_name' => $result->module_api_name,
                'module_name' => $result->module->name ?? $result->module_api_name,
                'module_icon' => $result->module->icon ?? 'file',
                'primary_value' => $result->primary_value,
                'secondary_value' => $result->secondary_value,
            ];
        });

        return response()->json([
            'results' => $formattedResults,
            'type' => 'results',
        ]);
    }

    /**
     * Get search suggestions.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        $suggestions = [];

        // Add matching recent searches
        if ($query) {
            $recentMatches = SearchHistory::where('user_id', Auth::id())
                ->whereRaw('LOWER(query) LIKE ?', ['%' . strtolower($query) . '%'])
                ->selectRaw('query, MAX(created_at) as last_searched')
                ->groupBy('query')
                ->orderByDesc('last_searched')
                ->limit(3)
                ->pluck('query')
                ->toArray();

            $suggestions = array_merge($suggestions, $recentMatches);
        }

        // Add matching saved searches
        $savedMatches = SavedSearch::where('user_id', Auth::id())
            ->when($query, function ($q) use ($query) {
                $q->whereRaw('LOWER(name) LIKE ? OR LOWER(query) LIKE ?', [
                    '%' . strtolower($query) . '%',
                    '%' . strtolower($query) . '%',
                ]);
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('use_count')
            ->limit(3)
            ->get()
            ->map(fn($s) => ['name' => $s->name, 'query' => $s->query, 'saved' => true])
            ->toArray();

        // Add modules for quick navigation
        $moduleMatches = [];
        if ($query) {
            $modules = Module::where('is_active', true)
                ->whereRaw('LOWER(name) LIKE ? OR LOWER(api_name) LIKE ?', [
                    '%' . strtolower($query) . '%',
                    '%' . strtolower($query) . '%',
                ])
                ->limit(3)
                ->get(['id', 'name', 'api_name', 'icon']);

            $moduleMatches = $modules->map(fn($m) => [
                'type' => 'module',
                'name' => $m->name,
                'api_name' => $m->api_name,
                'icon' => $m->icon,
            ])->toArray();
        }

        return response()->json([
            'recent' => $suggestions,
            'saved' => $savedMatches,
            'modules' => $moduleMatches,
        ]);
    }

    /**
     * Get recent search history.
     */
    public function history(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 20);

        $history = SearchHistory::getRecent(Auth::id(), $limit);

        return response()->json([
            'history' => $history->map(fn($h) => [
                'id' => $h->id,
                'query' => $h->query,
                'type' => $h->type,
                'module_api_name' => $h->module_api_name,
                'results_count' => $h->results_count,
                'created_at' => $h->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Clear search history.
     */
    public function clearHistory(): JsonResponse
    {
        SearchHistory::clearForUser(Auth::id());

        return response()->json([
            'message' => 'Search history cleared',
        ]);
    }

    /**
     * Get saved searches.
     */
    public function savedSearches(): JsonResponse
    {
        $searches = SavedSearch::getForUser(Auth::id());

        return response()->json([
            'data' => $searches->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'query' => $s->query,
                'type' => $s->type,
                'module_api_name' => $s->module_api_name,
                'filters' => $s->filters,
                'is_pinned' => $s->is_pinned,
                'use_count' => $s->use_count,
                'last_used_at' => $s->last_used_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Save a search.
     */
    public function saveSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'query' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'max:50'],
            'module_api_name' => ['nullable', 'string', 'max:100'],
            'filters' => ['nullable', 'array'],
            'is_pinned' => ['sometimes', 'boolean'],
        ]);

        $search = SavedSearch::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'query' => $validated['query'],
            'type' => $validated['type'] ?? 'global',
            'module_api_name' => $validated['module_api_name'] ?? null,
            'filters' => $validated['filters'] ?? null,
            'is_pinned' => $validated['is_pinned'] ?? false,
        ]);

        return response()->json([
            'message' => 'Search saved',
            'search' => [
                'id' => $search->id,
                'name' => $search->name,
                'query' => $search->query,
                'is_pinned' => $search->is_pinned,
            ],
        ], 201);
    }

    /**
     * Delete a saved search.
     */
    public function deleteSavedSearch(int $id): JsonResponse
    {
        $search = SavedSearch::where('user_id', Auth::id())
            ->findOrFail($id);

        $search->delete();

        return response()->json([
            'message' => 'Saved search deleted',
        ]);
    }

    /**
     * Toggle pin status of a saved search.
     */
    public function togglePin(int $id): JsonResponse
    {
        $search = SavedSearch::where('user_id', Auth::id())
            ->findOrFail($id);

        $search->togglePin();

        return response()->json([
            'message' => $search->is_pinned ? 'Search pinned' : 'Search unpinned',
            'is_pinned' => $search->is_pinned,
        ]);
    }

    /**
     * Reindex all records (admin only).
     */
    public function reindex(Request $request): JsonResponse
    {
        $moduleApiName = $request->input('module');

        if ($moduleApiName) {
            $module = Module::where('api_name', $moduleApiName)->firstOrFail();
            $count = SearchIndex::reindexModule($module);

            return response()->json([
                'message' => "Reindexed {$count} records for {$module->name}",
                'count' => $count,
            ]);
        }

        // Reindex all modules
        $totalCount = 0;
        $modules = Module::where('is_active', true)->get();

        foreach ($modules as $module) {
            $count = SearchIndex::reindexModule($module);
            $totalCount += $count;
        }

        return response()->json([
            'message' => "Reindexed {$totalCount} records across {$modules->count()} modules",
            'count' => $totalCount,
        ]);
    }

    /**
     * Get command palette options.
     */
    public function commands(): JsonResponse
    {
        $userId = Auth::id();

        // Get active modules for navigation
        $modules = Module::where('is_active', true)
            ->orderBy('display_order')
            ->get(['id', 'name', 'api_name', 'icon']);

        // Get quick actions
        $quickActions = [
            ['id' => 'create-record', 'name' => 'Create Record', 'icon' => 'plus', 'shortcut' => 'n'],
            ['id' => 'search', 'name' => 'Search', 'icon' => 'search', 'shortcut' => '/'],
            ['id' => 'settings', 'name' => 'Settings', 'icon' => 'settings', 'shortcut' => ','],
            ['id' => 'profile', 'name' => 'Profile', 'icon' => 'user', 'shortcut' => null],
            ['id' => 'logout', 'name' => 'Logout', 'icon' => 'log-out', 'shortcut' => null],
        ];

        // Get pinned saved searches
        $pinnedSearches = SavedSearch::getPinnedForUser($userId);

        return response()->json([
            'modules' => $modules->map(fn($m) => [
                'id' => 'module-' . $m->api_name,
                'name' => $m->name,
                'api_name' => $m->api_name,
                'icon' => $m->icon,
                'type' => 'navigation',
            ]),
            'actions' => $quickActions,
            'pinned_searches' => $pinnedSearches->map(fn($s) => [
                'id' => 'search-' . $s->id,
                'name' => $s->name,
                'query' => $s->query,
                'type' => 'saved_search',
            ]),
        ]);
    }
}
