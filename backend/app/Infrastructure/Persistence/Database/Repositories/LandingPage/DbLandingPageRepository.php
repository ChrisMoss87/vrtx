<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\LandingPage;

use App\Domain\LandingPage\Repositories\LandingPageRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbLandingPageRepository implements LandingPageRepositoryInterface
{
    private const TABLE_LANDING_PAGES = 'landing_pages';
    private const TABLE_LANDING_PAGE_TEMPLATES = 'landing_page_templates';
    private const TABLE_LANDING_PAGE_VARIANTS = 'landing_page_variants';
    private const TABLE_LANDING_PAGE_ANALYTICS = 'landing_page_analytics';
    private const TABLE_LANDING_PAGE_VISITS = 'landing_page_visits';

    /**
     * Convert stdClass to array recursively
     */
    private function toArray(stdClass|array|null $data): ?array
    {
        if ($data === null) {
            return null;
        }

        if (is_array($data)) {
            return array_map(fn($item) => $this->toArray($item), $data);
        }

        $array = (array) $data;

        // Decode JSON fields for landing pages
        if (isset($array['content']) && is_string($array['content'])) {
            $array['content'] = json_decode($array['content'], true);
        }
        if (isset($array['settings']) && is_string($array['settings'])) {
            $array['settings'] = json_decode($array['settings'], true);
        }
        if (isset($array['seo_settings']) && is_string($array['seo_settings'])) {
            $array['seo_settings'] = json_decode($array['seo_settings'], true);
        }
        if (isset($array['styles']) && is_string($array['styles'])) {
            $array['styles'] = json_decode($array['styles'], true);
        }

        // Decode JSON fields for analytics
        if (isset($array['referrer_breakdown']) && is_string($array['referrer_breakdown'])) {
            $array['referrer_breakdown'] = json_decode($array['referrer_breakdown'], true);
        }
        if (isset($array['device_breakdown']) && is_string($array['device_breakdown'])) {
            $array['device_breakdown'] = json_decode($array['device_breakdown'], true);
        }
        if (isset($array['location_breakdown']) && is_string($array['location_breakdown'])) {
            $array['location_breakdown'] = json_decode($array['location_breakdown'], true);
        }

        return $array;
    }

    /**
     * Load relations for a record
     */
    private function loadRelations(array $records, array $relations, string $foreignKey, string $relatedTable, string $relatedKey = 'id', string $relationName = null): array
    {
        if (empty($records) || empty($relations)) {
            return $records;
        }

        $relationName = $relationName ?? str_replace('_id', '', $foreignKey);

        // Get unique foreign key values
        $foreignIds = array_filter(array_unique(array_column($records, $foreignKey)));

        if (empty($foreignIds)) {
            return $records;
        }

        // Load related records
        $relatedRecords = DB::table($relatedTable)
            ->whereIn($relatedKey, $foreignIds)
            ->get()
            ->keyBy($relatedKey);

        // Attach relations to records
        foreach ($records as &$record) {
            if (isset($record[$foreignKey]) && isset($relatedRecords[$record[$foreignKey]])) {
                $record[$relationName] = $this->toArray($relatedRecords[$record[$foreignKey]]);
            } else {
                $record[$relationName] = null;
            }
        }

        return $records;
    }
    // =========================================================================
    // QUERY METHODS - LANDING PAGES
    // =========================================================================

    public function listPages(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_LANDING_PAGES)
            ->whereNull('deleted_at');

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by campaign
        if (!empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        // Filter by template
        if (!empty($filters['template_id'])) {
            $query->where('template_id', $filters['template_id']);
        }

        // Filter by A/B testing enabled
        if (isset($filters['ab_testing'])) {
            $query->where('is_ab_testing_enabled', $filters['ab_testing']);
        }

        // Filter by creator
        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Get total count
        $total = $query->count();

        // Get paginated data
        $items = $query
            ->forPage($page, $perPage)
            ->get()
            ->map(fn($item) => $this->toArray($item))
            ->toArray();

        // Load relations
        if (!empty($items)) {
            $items = $this->loadRelations($items, ['template'], 'template_id', self::TABLE_LANDING_PAGE_TEMPLATES, 'id', 'template');
            $items = $this->loadRelations($items, ['creator'], 'created_by', 'users', 'id', 'creator');
            $items = $this->loadRelations($items, ['campaign'], 'campaign_id', 'campaigns', 'id', 'campaign');
        }

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getPageById(int $id, array $relations = []): ?array
    {
        $page = DB::table(self::TABLE_LANDING_PAGES)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$page) {
            return null;
        }

        $result = $this->toArray($page);

        // Load relations if requested
        if (!empty($relations)) {
            foreach ($relations as $relation) {
                if ($relation === 'template' && isset($result['template_id'])) {
                    $template = DB::table(self::TABLE_LANDING_PAGE_TEMPLATES)
                        ->where('id', $result['template_id'])
                        ->first();
                    $result['template'] = $this->toArray($template);
                } elseif ($relation === 'creator' && isset($result['created_by'])) {
                    $creator = DB::table('users')
                        ->where('id', $result['created_by'])
                        ->first();
                    $result['creator'] = $this->toArray($creator);
                } elseif ($relation === 'campaign' && isset($result['campaign_id'])) {
                    $campaign = DB::table('campaigns')
                        ->where('id', $result['campaign_id'])
                        ->first();
                    $result['campaign'] = $this->toArray($campaign);
                } elseif ($relation === 'variants') {
                    $variants = DB::table(self::TABLE_LANDING_PAGE_VARIANTS)
                        ->where('page_id', $id)
                        ->get()
                        ->map(fn($v) => $this->toArray($v))
                        ->toArray();
                    $result['variants'] = $variants;
                }
            }
        }

        return $result;
    }

    public function getPageBySlug(string $slug, array $relations = []): ?array
    {
        $page = DB::table(self::TABLE_LANDING_PAGES)
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->first();

        if (!$page) {
            return null;
        }

        $result = $this->toArray($page);

        // Load relations if requested
        if (!empty($relations)) {
            foreach ($relations as $relation) {
                if ($relation === 'template' && isset($result['template_id'])) {
                    $template = DB::table(self::TABLE_LANDING_PAGE_TEMPLATES)
                        ->where('id', $result['template_id'])
                        ->first();
                    $result['template'] = $this->toArray($template);
                } elseif ($relation === 'creator' && isset($result['created_by'])) {
                    $creator = DB::table('users')
                        ->where('id', $result['created_by'])
                        ->first();
                    $result['creator'] = $this->toArray($creator);
                } elseif ($relation === 'campaign' && isset($result['campaign_id'])) {
                    $campaign = DB::table('campaigns')
                        ->where('id', $result['campaign_id'])
                        ->first();
                    $result['campaign'] = $this->toArray($campaign);
                } elseif ($relation === 'variants') {
                    $variants = DB::table(self::TABLE_LANDING_PAGE_VARIANTS)
                        ->where('page_id', $result['id'])
                        ->get()
                        ->map(fn($v) => $this->toArray($v))
                        ->toArray();
                    $result['variants'] = $variants;
                }
            }
        }

        return $result;
    }

    public function getPublishedPages(array $relations = []): array
    {
        $pages = DB::table(self::TABLE_LANDING_PAGES)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->orderBy('published_at', 'desc')
            ->get()
            ->map(fn($item) => $this->toArray($item))
            ->toArray();

        if (empty($pages)) {
            return [];
        }

        // Load default template relation
        $pages = $this->loadRelations($pages, ['template'], 'template_id', self::TABLE_LANDING_PAGE_TEMPLATES, 'id', 'template');

        // Load additional relations if requested
        if (!empty($relations)) {
            foreach ($relations as $relation) {
                if ($relation === 'creator') {
                    $pages = $this->loadRelations($pages, ['creator'], 'created_by', 'users', 'id', 'creator');
                } elseif ($relation === 'campaign') {
                    $pages = $this->loadRelations($pages, ['campaign'], 'campaign_id', 'campaigns', 'id', 'campaign');
                }
            }
        }

        return $pages;
    }

    public function getDraftPages(array $relations = []): array
    {
        $pages = DB::table(self::TABLE_LANDING_PAGES)
            ->where('status', 'draft')
            ->whereNull('deleted_at')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn($item) => $this->toArray($item))
            ->toArray();

        if (empty($pages)) {
            return [];
        }

        // Load default creator relation
        $pages = $this->loadRelations($pages, ['creator'], 'created_by', 'users', 'id', 'creator');

        // Load additional relations if requested
        if (!empty($relations)) {
            foreach ($relations as $relation) {
                if ($relation === 'template') {
                    $pages = $this->loadRelations($pages, ['template'], 'template_id', self::TABLE_LANDING_PAGE_TEMPLATES, 'id', 'template');
                } elseif ($relation === 'campaign') {
                    $pages = $this->loadRelations($pages, ['campaign'], 'campaign_id', 'campaigns', 'id', 'campaign');
                }
            }
        }

        return $pages;
    }

    // =========================================================================
    // QUERY METHODS - TEMPLATES
    // =========================================================================

    public function listTemplates(array $filters = []): array
    {
        $query = DB::table(self::TABLE_LANDING_PAGE_TEMPLATES)
            ->where('is_active', true);

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('usage_count', 'desc')
            ->get()
            ->map(fn($item) => $this->toArray($item))
            ->toArray();
    }

    public function getTemplateById(int $id, array $relations = []): ?array
    {
        $template = DB::table(self::TABLE_LANDING_PAGE_TEMPLATES)
            ->where('id', $id)
            ->first();

        if (!$template) {
            return null;
        }

        $result = $this->toArray($template);

        // Load relations if requested
        if (!empty($relations)) {
            foreach ($relations as $relation) {
                if ($relation === 'creator' && isset($result['created_by'])) {
                    $creator = DB::table('users')
                        ->where('id', $result['created_by'])
                        ->first();
                    $result['creator'] = $this->toArray($creator);
                }
            }
        }

        return $result;
    }

    // =========================================================================
    // QUERY METHODS - VARIANTS
    // =========================================================================

    public function getVariantsByPageId(int $pageId): array
    {
        return DB::table(self::TABLE_LANDING_PAGE_VARIANTS)
            ->where('page_id', $pageId)
            ->orderBy('variant_code')
            ->get()
            ->map(fn($item) => $this->toArray($item))
            ->toArray();
    }

    public function getVariantById(int $id, array $relations = []): ?array
    {
        $variant = DB::table(self::TABLE_LANDING_PAGE_VARIANTS)
            ->where('id', $id)
            ->first();

        if (!$variant) {
            return null;
        }

        $result = $this->toArray($variant);

        // Load relations if requested
        if (!empty($relations)) {
            foreach ($relations as $relation) {
                if ($relation === 'page' && isset($result['page_id'])) {
                    $page = DB::table(self::TABLE_LANDING_PAGES)
                        ->where('id', $result['page_id'])
                        ->first();
                    $result['page'] = $this->toArray($page);
                }
            }
        }

        return $result;
    }

    // =========================================================================
    // QUERY METHODS - ANALYTICS
    // =========================================================================

    public function getPageAnalytics(int $pageId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)
            ->where('page_id', $pageId);

        if ($dateFrom) {
            $query->where('date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('date', '<=', $dateTo);
        }

        return $query->orderBy('date', 'desc')
            ->get()
            ->map(fn($item) => $this->toArray($item))
            ->toArray();
    }

    public function getVariantAnalytics(int $pageId): array
    {
        // Get all variants for the page
        $variants = DB::table(self::TABLE_LANDING_PAGE_VARIANTS)
            ->where('page_id', $pageId)
            ->get();

        $results = [];
        foreach ($variants as $variant) {
            // Get total views and conversions from analytics
            $analytics = DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)
                ->where('variant_id', $variant->id)
                ->selectRaw('SUM(views) as total_views, SUM(form_submissions) as total_conversions')
                ->first();

            $totalViews = $analytics->total_views ?? 0;
            $totalConversions = $analytics->total_conversions ?? 0;
            $conversionRate = $totalViews > 0
                ? round(($totalConversions / $totalViews) * 100, 2)
                : 0;

            $results[] = [
                'variant_id' => $variant->id,
                'variant_code' => $variant->variant_code,
                'name' => $variant->name,
                'is_active' => (bool) $variant->is_active,
                'is_winner' => (bool) $variant->is_winner,
                'traffic_percentage' => $variant->traffic_percentage,
                'views' => (int) $totalViews,
                'conversions' => (int) $totalConversions,
                'conversion_rate' => $conversionRate,
            ];
        }

        return $results;
    }

    public function getPageSummary(int $pageId): array
    {
        // Get total views and conversions from analytics
        $totals = DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)
            ->where('page_id', $pageId)
            ->selectRaw('SUM(views) as total_views, SUM(form_submissions) as total_conversions, SUM(unique_visitors) as unique_visitors, SUM(bounces) as bounces')
            ->first();

        $totalViews = $totals->total_views ?? 0;
        $totalConversions = $totals->total_conversions ?? 0;
        $conversionRate = $totalViews > 0
            ? round(($totalConversions / $totalViews) * 100, 2)
            : 0;

        $bounceRate = $totalViews > 0
            ? round(($totals->bounces / $totalViews) * 100, 2)
            : 0;

        $avgTimeOnPage = DB::table(self::TABLE_LANDING_PAGE_VISITS)
            ->where('page_id', $pageId)
            ->whereNotNull('time_on_page')
            ->avg('time_on_page') ?? 0;

        return [
            'page_id' => $pageId,
            'total_views' => (int) $totalViews,
            'unique_visitors' => (int) ($totals->unique_visitors ?? 0),
            'total_conversions' => (int) $totalConversions,
            'conversion_rate' => $conversionRate,
            'bounce_rate' => $bounceRate,
            'avg_time_on_page' => round($avgTimeOnPage, 2),
        ];
    }

    public function getPageTimeSeries(int $pageId, int $days = 30): array
    {
        $startDate = now()->subDays($days)->toDateString();

        $data = DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)
            ->where('page_id', $pageId)
            ->where('date', '>=', $startDate)
            ->orderBy('date')
            ->get();

        return [
            'labels' => $data->pluck('date')->toArray(),
            'views' => $data->pluck('views')->toArray(),
            'conversions' => $data->pluck('form_submissions')->toArray(),
            'unique_visitors' => $data->pluck('unique_visitors')->toArray(),
        ];
    }

    public function getTopReferrers(int $pageId, int $limit = 10): array
    {
        $analytics = DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)
            ->where('page_id', $pageId)
            ->whereNotNull('referrer_breakdown')
            ->get();

        $referrers = [];
        foreach ($analytics as $record) {
            $breakdown = is_string($record->referrer_breakdown)
                ? json_decode($record->referrer_breakdown, true)
                : $record->referrer_breakdown;

            foreach ($breakdown ?? [] as $referrer => $count) {
                if (!isset($referrers[$referrer])) {
                    $referrers[$referrer] = 0;
                }
                $referrers[$referrer] += $count;
            }
        }

        arsort($referrers);
        return array_slice($referrers, 0, $limit, true);
    }

    public function getDeviceBreakdown(int $pageId): array
    {
        $analytics = DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)
            ->where('page_id', $pageId)
            ->whereNotNull('device_breakdown')
            ->get();

        $devices = [];
        foreach ($analytics as $record) {
            $breakdown = is_string($record->device_breakdown)
                ? json_decode($record->device_breakdown, true)
                : $record->device_breakdown;

            foreach ($breakdown ?? [] as $device => $count) {
                if (!isset($devices[$device])) {
                    $devices[$device] = 0;
                }
                $devices[$device] += $count;
            }
        }

        return $devices;
    }

    public function getLocationBreakdown(int $pageId, int $limit = 20): array
    {
        $analytics = DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)
            ->where('page_id', $pageId)
            ->whereNotNull('location_breakdown')
            ->get();

        $locations = [];
        foreach ($analytics as $record) {
            $breakdown = is_string($record->location_breakdown)
                ? json_decode($record->location_breakdown, true)
                : $record->location_breakdown;

            foreach ($breakdown ?? [] as $location => $count) {
                if (!isset($locations[$location])) {
                    $locations[$location] = 0;
                }
                $locations[$location] += $count;
            }
        }

        arsort($locations);
        return array_slice($locations, 0, $limit, true);
    }

    // =========================================================================
    // QUERY METHODS - VISITS
    // =========================================================================

    public function getRecentVisits(int $pageId, int $limit = 100): array
    {
        $visits = DB::table(self::TABLE_LANDING_PAGE_VISITS)
            ->where('page_id', $pageId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($item) => $this->toArray($item))
            ->toArray();

        if (empty($visits)) {
            return [];
        }

        // Load variant relation
        return $this->loadRelations($visits, ['variant'], 'variant_id', self::TABLE_LANDING_PAGE_VARIANTS, 'id', 'variant');
    }

    public function getConvertedVisits(int $pageId): array
    {
        $visits = DB::table(self::TABLE_LANDING_PAGE_VISITS)
            ->where('page_id', $pageId)
            ->where('converted', true)
            ->orderBy('converted_at', 'desc')
            ->get()
            ->map(fn($item) => $this->toArray($item))
            ->toArray();

        if (empty($visits)) {
            return [];
        }

        // Load variant and submission relations
        $visits = $this->loadRelations($visits, ['variant'], 'variant_id', self::TABLE_LANDING_PAGE_VARIANTS, 'id', 'variant');
        $visits = $this->loadRelations($visits, ['submission'], 'submission_id', 'web_form_submissions', 'id', 'submission');

        return $visits;
    }

    // =========================================================================
    // QUERY METHODS - REPORTING
    // =========================================================================

    public function getPerformanceOverview(array $filters = []): array
    {
        $query = DB::table(self::TABLE_LANDING_PAGES)
            ->whereNull('deleted_at');

        if (!empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $pages = $query->get();

        $totalViews = 0;
        $totalConversions = 0;
        $pageStats = [];

        foreach ($pages as $page) {
            // Get analytics for this page
            $analytics = DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)
                ->where('page_id', $page->id)
                ->selectRaw('SUM(views) as total_views, SUM(form_submissions) as total_conversions')
                ->first();

            $views = $analytics->total_views ?? 0;
            $conversions = $analytics->total_conversions ?? 0;
            $conversionRate = $views > 0
                ? round(($conversions / $views) * 100, 2)
                : 0;

            $totalViews += $views;
            $totalConversions += $conversions;

            $pageStats[] = [
                'id' => $page->id,
                'name' => $page->name,
                'status' => $page->status,
                'views' => (int) $views,
                'conversions' => (int) $conversions,
                'conversion_rate' => $conversionRate,
            ];
        }

        // Sort by conversion rate
        usort($pageStats, fn($a, $b) => $b['conversion_rate'] <=> $a['conversion_rate']);

        return [
            'total_pages' => count($pages),
            'total_views' => (int) $totalViews,
            'total_conversions' => (int) $totalConversions,
            'average_conversion_rate' => $totalViews > 0
                ? round(($totalConversions / $totalViews) * 100, 2)
                : 0,
            'pages' => $pageStats,
            'top_performers' => array_slice($pageStats, 0, 5),
        ];
    }

    // =========================================================================
    // COMMAND METHODS - LANDING PAGES
    // =========================================================================

    public function createPage(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $now = now()->toDateTimeString();

            $pageData = [
                'name' => $data['name'],
                'slug' => $data['slug'] ?? \Illuminate\Support\Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'template_id' => $data['template_id'] ?? null,
                'content' => json_encode($data['content'] ?? []),
                'settings' => json_encode($data['settings'] ?? []),
                'seo_settings' => json_encode($data['seo_settings'] ?? []),
                'styles' => json_encode($data['styles'] ?? []),
                'custom_domain' => $data['custom_domain'] ?? null,
                'favicon_url' => $data['favicon_url'] ?? null,
                'og_image_url' => $data['og_image_url'] ?? null,
                'web_form_id' => $data['web_form_id'] ?? null,
                'thank_you_page_type' => $data['thank_you_page_type'] ?? 'message',
                'thank_you_message' => $data['thank_you_message'] ?? null,
                'thank_you_redirect_url' => $data['thank_you_redirect_url'] ?? null,
                'thank_you_page_id' => $data['thank_you_page_id'] ?? null,
                'is_ab_testing_enabled' => $data['is_ab_testing_enabled'] ?? false,
                'campaign_id' => $data['campaign_id'] ?? null,
                'created_by' => $data['created_by'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $pageId = DB::table(self::TABLE_LANDING_PAGES)->insertGetId($pageData);

            // Increment template usage if template was used
            if (!empty($data['template_id'])) {
                DB::table(self::TABLE_LANDING_PAGE_TEMPLATES)
                    ->where('id', $data['template_id'])
                    ->increment('usage_count');
            }

            return $this->getPageById($pageId);
        });
    }

    public function updatePage(int $id, array $data): array
    {
        $updateData = array_filter([
            'name' => $data['name'] ?? null,
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? null,
            'content' => isset($data['content']) ? json_encode($data['content']) : null,
            'settings' => isset($data['settings']) ? json_encode($data['settings']) : null,
            'seo_settings' => isset($data['seo_settings']) ? json_encode($data['seo_settings']) : null,
            'styles' => isset($data['styles']) ? json_encode($data['styles']) : null,
            'custom_domain' => $data['custom_domain'] ?? null,
            'favicon_url' => $data['favicon_url'] ?? null,
            'og_image_url' => $data['og_image_url'] ?? null,
            'web_form_id' => $data['web_form_id'] ?? null,
            'thank_you_page_type' => $data['thank_you_page_type'] ?? null,
            'thank_you_message' => $data['thank_you_message'] ?? null,
            'thank_you_redirect_url' => $data['thank_you_redirect_url'] ?? null,
            'thank_you_page_id' => $data['thank_you_page_id'] ?? null,
            'is_ab_testing_enabled' => $data['is_ab_testing_enabled'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
        ], fn($value) => $value !== null);

        if (!empty($updateData)) {
            $updateData['updated_at'] = now()->toDateTimeString();

            DB::table(self::TABLE_LANDING_PAGES)
                ->where('id', $id)
                ->update($updateData);
        }

        return $this->getPageById($id);
    }

    public function deletePage(int $id): bool
    {
        $deleted = DB::table(self::TABLE_LANDING_PAGES)
            ->where('id', $id)
            ->update([
                'deleted_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);

        return $deleted > 0;
    }

    public function publishPage(int $id): array
    {
        DB::table(self::TABLE_LANDING_PAGES)
            ->where('id', $id)
            ->update([
                'status' => 'published',
                'published_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);

        return $this->getPageById($id);
    }

    public function unpublishPage(int $id): array
    {
        DB::table(self::TABLE_LANDING_PAGES)
            ->where('id', $id)
            ->update([
                'status' => 'draft',
                'updated_at' => now()->toDateTimeString(),
            ]);

        return $this->getPageById($id);
    }

    public function archivePage(int $id): array
    {
        DB::table(self::TABLE_LANDING_PAGES)
            ->where('id', $id)
            ->update([
                'status' => 'archived',
                'updated_at' => now()->toDateTimeString(),
            ]);

        return $this->getPageById($id);
    }

    public function duplicatePage(int $id, string $newName, int $userId): array
    {
        $original = DB::table(self::TABLE_LANDING_PAGES)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$original) {
            throw new \InvalidArgumentException("Landing page with ID {$id} not found");
        }

        return DB::transaction(function () use ($original, $newName, $userId) {
            $now = now()->toDateTimeString();

            // Prepare duplicate data
            $duplicateData = [
                'name' => $newName,
                'slug' => \Illuminate\Support\Str::slug($newName),
                'description' => $original->description,
                'status' => 'draft',
                'template_id' => $original->template_id,
                'content' => $original->content,
                'settings' => $original->settings,
                'seo_settings' => $original->seo_settings,
                'styles' => $original->styles,
                'custom_domain' => null,
                'custom_domain_verified' => false,
                'favicon_url' => $original->favicon_url,
                'og_image_url' => $original->og_image_url,
                'web_form_id' => $original->web_form_id,
                'thank_you_page_type' => $original->thank_you_page_type,
                'thank_you_message' => $original->thank_you_message,
                'thank_you_redirect_url' => $original->thank_you_redirect_url,
                'thank_you_page_id' => $original->thank_you_page_id,
                'is_ab_testing_enabled' => $original->is_ab_testing_enabled,
                'campaign_id' => $original->campaign_id,
                'published_at' => null,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $duplicateId = DB::table(self::TABLE_LANDING_PAGES)->insertGetId($duplicateData);

            // Duplicate variants if A/B testing is enabled
            if ($original->is_ab_testing_enabled) {
                $variants = DB::table(self::TABLE_LANDING_PAGE_VARIANTS)
                    ->where('page_id', $id)
                    ->get();

                foreach ($variants as $variant) {
                    DB::table(self::TABLE_LANDING_PAGE_VARIANTS)->insert([
                        'page_id' => $duplicateId,
                        'name' => $variant->name,
                        'variant_code' => $variant->variant_code,
                        'content' => $variant->content,
                        'styles' => $variant->styles,
                        'traffic_percentage' => $variant->traffic_percentage,
                        'is_active' => $variant->is_active,
                        'is_winner' => false,
                        'declared_winner_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            return $this->getPageById($duplicateId);
        });
    }

    // =========================================================================
    // COMMAND METHODS - TEMPLATES
    // =========================================================================

    public function createTemplate(array $data): array
    {
        $now = now()->toDateTimeString();

        $templateData = [
            'name' => $data['name'],
            'category' => $data['category'] ?? 'general',
            'description' => $data['description'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'content' => json_encode($data['content'] ?? []),
            'styles' => json_encode($data['styles'] ?? []),
            'is_system' => $data['is_system'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'usage_count' => 0,
            'created_by' => $data['created_by'],
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $templateId = DB::table(self::TABLE_LANDING_PAGE_TEMPLATES)->insertGetId($templateData);

        return $this->getTemplateById($templateId);
    }

    public function updateTemplate(int $id, array $data): array
    {
        $updateData = array_filter([
            'name' => $data['name'] ?? null,
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'content' => isset($data['content']) ? json_encode($data['content']) : null,
            'styles' => isset($data['styles']) ? json_encode($data['styles']) : null,
            'is_active' => $data['is_active'] ?? null,
        ], fn($value) => $value !== null);

        if (!empty($updateData)) {
            $updateData['updated_at'] = now()->toDateTimeString();

            DB::table(self::TABLE_LANDING_PAGE_TEMPLATES)
                ->where('id', $id)
                ->update($updateData);
        }

        return $this->getTemplateById($id);
    }

    public function deleteTemplate(int $id): bool
    {
        $template = DB::table(self::TABLE_LANDING_PAGE_TEMPLATES)
            ->where('id', $id)
            ->first();

        if (!$template) {
            throw new \InvalidArgumentException("Template with ID {$id} not found");
        }

        // Don't allow deleting system templates
        if ($template->is_system) {
            throw new \InvalidArgumentException('Cannot delete system templates');
        }

        $deleted = DB::table(self::TABLE_LANDING_PAGE_TEMPLATES)
            ->where('id', $id)
            ->delete();

        return $deleted > 0;
    }

    // =========================================================================
    // COMMAND METHODS - VARIANTS
    // =========================================================================

    public function createVariant(int $pageId, array $data): array
    {
        $page = DB::table(self::TABLE_LANDING_PAGES)
            ->where('id', $pageId)
            ->whereNull('deleted_at')
            ->first();

        if (!$page) {
            throw new \InvalidArgumentException("Landing page with ID {$pageId} not found");
        }

        // Enable A/B testing on the page
        if (!$page->is_ab_testing_enabled) {
            DB::table(self::TABLE_LANDING_PAGES)
                ->where('id', $pageId)
                ->update([
                    'is_ab_testing_enabled' => true,
                    'updated_at' => now()->toDateTimeString(),
                ]);
        }

        $now = now()->toDateTimeString();

        $variantData = [
            'page_id' => $pageId,
            'name' => $data['name'],
            'variant_code' => $data['variant_code'] ?? strtoupper(substr(uniqid(), -3)),
            'content' => isset($data['content']) ? json_encode($data['content']) : $page->content,
            'styles' => isset($data['styles']) ? json_encode($data['styles']) : $page->styles,
            'traffic_percentage' => $data['traffic_percentage'] ?? 50,
            'is_active' => $data['is_active'] ?? true,
            'is_winner' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $variantId = DB::table(self::TABLE_LANDING_PAGE_VARIANTS)->insertGetId($variantData);

        return $this->getVariantById($variantId);
    }

    public function updateVariant(int $id, array $data): array
    {
        $updateData = array_filter([
            'name' => $data['name'] ?? null,
            'content' => isset($data['content']) ? json_encode($data['content']) : null,
            'styles' => isset($data['styles']) ? json_encode($data['styles']) : null,
            'traffic_percentage' => $data['traffic_percentage'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn($value) => $value !== null);

        if (!empty($updateData)) {
            $updateData['updated_at'] = now()->toDateTimeString();

            DB::table(self::TABLE_LANDING_PAGE_VARIANTS)
                ->where('id', $id)
                ->update($updateData);
        }

        return $this->getVariantById($id);
    }

    public function deleteVariant(int $id): bool
    {
        $deleted = DB::table(self::TABLE_LANDING_PAGE_VARIANTS)
            ->where('id', $id)
            ->delete();

        return $deleted > 0;
    }

    public function declareVariantWinner(int $variantId): array
    {
        return DB::transaction(function () use ($variantId) {
            $variant = DB::table(self::TABLE_LANDING_PAGE_VARIANTS)
                ->where('id', $variantId)
                ->first();

            if (!$variant) {
                throw new \InvalidArgumentException("Variant with ID {$variantId} not found");
            }

            $now = now()->toDateTimeString();

            // Mark all other variants as not winner and inactive
            DB::table(self::TABLE_LANDING_PAGE_VARIANTS)
                ->where('page_id', $variant->page_id)
                ->where('id', '!=', $variantId)
                ->update([
                    'is_winner' => false,
                    'is_active' => false,
                    'updated_at' => $now,
                ]);

            // Mark this variant as winner
            DB::table(self::TABLE_LANDING_PAGE_VARIANTS)
                ->where('id', $variantId)
                ->update([
                    'is_winner' => true,
                    'traffic_percentage' => 100,
                    'declared_winner_at' => $now,
                    'updated_at' => $now,
                ]);

            // Update main page content with winning variant
            DB::table(self::TABLE_LANDING_PAGES)
                ->where('id', $variant->page_id)
                ->update([
                    'content' => $variant->content,
                    'styles' => $variant->styles,
                    'is_ab_testing_enabled' => false,
                    'updated_at' => $now,
                ]);

            return $this->getVariantById($variantId);
        });
    }

    // =========================================================================
    // COMMAND METHODS - VISIT TRACKING
    // =========================================================================

    public function recordVisit(int $pageId, array $data): array
    {
        $page = DB::table(self::TABLE_LANDING_PAGES)
            ->where('id', $pageId)
            ->whereNull('deleted_at')
            ->first();

        if (!$page) {
            throw new \InvalidArgumentException("Landing page with ID {$pageId} not found");
        }

        // Parse user agent
        $userAgentData = $this->parseUserAgent($data['user_agent'] ?? '');

        $now = now()->toDateTimeString();

        // Create visit record
        $visitData = [
            'page_id' => $pageId,
            'variant_id' => $data['variant_id'] ?? null,
            'visitor_id' => $data['visitor_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'referrer' => $data['referrer'] ?? null,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
            'device_type' => $userAgentData['device_type'],
            'browser' => $userAgentData['browser'],
            'os' => $userAgentData['os'],
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'converted' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $visitId = DB::table(self::TABLE_LANDING_PAGE_VISITS)->insertGetId($visitData);

        // Record in analytics
        $this->recordAnalyticsView($pageId, $data['variant_id'] ?? null, [
            'referrer' => $data['referrer'] ?? null,
            'device_type' => $userAgentData['device_type'],
            'country' => $data['country'] ?? null,
        ]);

        $visit = DB::table(self::TABLE_LANDING_PAGE_VISITS)->where('id', $visitId)->first();
        return $this->toArray($visit);
    }

    public function updateVisitEngagement(int $visitId, int $timeOnPage, int $scrollDepth): array
    {
        $visit = DB::table(self::TABLE_LANDING_PAGE_VISITS)
            ->where('id', $visitId)
            ->first();

        if (!$visit) {
            throw new \InvalidArgumentException("Visit with ID {$visitId} not found");
        }

        DB::table(self::TABLE_LANDING_PAGE_VISITS)
            ->where('id', $visitId)
            ->update([
                'time_on_page' => $timeOnPage,
                'scroll_depth' => $scrollDepth,
                'updated_at' => now()->toDateTimeString(),
            ]);

        // Record bounce if time on page is less than 10 seconds
        if ($timeOnPage < 10) {
            $this->recordAnalyticsBounce($visit->page_id, $visit->variant_id);
        }

        $updatedVisit = DB::table(self::TABLE_LANDING_PAGE_VISITS)->where('id', $visitId)->first();
        return $this->toArray($updatedVisit);
    }

    public function markVisitConverted(int $visitId, int $submissionId): array
    {
        $visit = DB::table(self::TABLE_LANDING_PAGE_VISITS)
            ->where('id', $visitId)
            ->first();

        if (!$visit) {
            throw new \InvalidArgumentException("Visit with ID {$visitId} not found");
        }

        $now = now()->toDateTimeString();

        DB::table(self::TABLE_LANDING_PAGE_VISITS)
            ->where('id', $visitId)
            ->update([
                'converted' => true,
                'converted_at' => $now,
                'submission_id' => $submissionId,
                'updated_at' => $now,
            ]);

        // Update analytics
        $this->recordAnalyticsConversion($visit->page_id, $visit->variant_id);

        $updatedVisit = DB::table(self::TABLE_LANDING_PAGE_VISITS)->where('id', $visitId)->first();
        return $this->toArray($updatedVisit);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function parseUserAgent(string $userAgent): array
    {
        $deviceType = 'desktop';
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            $deviceType = preg_match('/iPad|Tablet/', $userAgent) ? 'tablet' : 'mobile';
        }

        $browser = 'Unknown';
        if (preg_match('/Chrome\/[\d.]+/', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox\/[\d.]+/', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari\/[\d.]+/', $userAgent) && !preg_match('/Chrome/', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge\/[\d.]+/', $userAgent)) {
            $browser = 'Edge';
        }

        $os = 'Unknown';
        if (preg_match('/Windows NT/', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS X/', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad/', $userAgent)) {
            $os = 'iOS';
        }

        return [
            'device_type' => $deviceType,
            'browser' => $browser,
            'os' => $os,
        ];
    }

    private function recordAnalyticsView(int $pageId, ?int $variantId, array $metadata): void
    {
        $date = now()->toDateString();

        // Try to find existing analytics record
        $analytics = DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)
            ->where('page_id', $pageId)
            ->where(function ($q) use ($variantId) {
                if ($variantId === null) {
                    $q->whereNull('variant_id');
                } else {
                    $q->where('variant_id', $variantId);
                }
            })
            ->where('date', $date)
            ->first();

        if (!$analytics) {
            // Create new analytics record
            DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)->insert([
                'page_id' => $pageId,
                'variant_id' => $variantId,
                'date' => $date,
                'views' => 1,
                'unique_visitors' => 0,
                'form_submissions' => 0,
                'bounces' => 0,
                'avg_time_on_page' => 0,
                'referrer_breakdown' => json_encode([]),
                'device_breakdown' => json_encode([]),
                'location_breakdown' => json_encode([]),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);

            $analytics = DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)
                ->where('page_id', $pageId)
                ->where(function ($q) use ($variantId) {
                    if ($variantId === null) {
                        $q->whereNull('variant_id');
                    } else {
                        $q->where('variant_id', $variantId);
                    }
                })
                ->where('date', $date)
                ->first();
        } else {
            // Increment views
            DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)
                ->where('id', $analytics->id)
                ->increment('views');
        }

        // Update breakdowns
        $updates = [];

        if (!empty($metadata['referrer'])) {
            $breakdown = is_string($analytics->referrer_breakdown)
                ? json_decode($analytics->referrer_breakdown, true)
                : [];
            $referrer = parse_url($metadata['referrer'], PHP_URL_HOST) ?? 'direct';
            $breakdown[$referrer] = ($breakdown[$referrer] ?? 0) + 1;
            $updates['referrer_breakdown'] = json_encode($breakdown);
        }

        if (!empty($metadata['device_type'])) {
            $breakdown = is_string($analytics->device_breakdown)
                ? json_decode($analytics->device_breakdown, true)
                : [];
            $breakdown[$metadata['device_type']] = ($breakdown[$metadata['device_type']] ?? 0) + 1;
            $updates['device_breakdown'] = json_encode($breakdown);
        }

        if (!empty($metadata['country'])) {
            $breakdown = is_string($analytics->location_breakdown)
                ? json_decode($analytics->location_breakdown, true)
                : [];
            $breakdown[$metadata['country']] = ($breakdown[$metadata['country']] ?? 0) + 1;
            $updates['location_breakdown'] = json_encode($breakdown);
        }

        if (!empty($updates)) {
            $updates['updated_at'] = now()->toDateTimeString();
            DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)
                ->where('id', $analytics->id)
                ->update($updates);
        }
    }

    private function recordAnalyticsConversion(int $pageId, ?int $variantId): void
    {
        $date = now()->toDateString();

        DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)
            ->where('page_id', $pageId)
            ->where(function ($q) use ($variantId) {
                if ($variantId === null) {
                    $q->whereNull('variant_id');
                } else {
                    $q->where('variant_id', $variantId);
                }
            })
            ->where('date', $date)
            ->increment('form_submissions');
    }

    private function recordAnalyticsBounce(int $pageId, ?int $variantId): void
    {
        $date = now()->toDateString();

        DB::table(self::TABLE_LANDING_PAGE_ANALYTICS)
            ->where('page_id', $pageId)
            ->where(function ($q) use ($variantId) {
                if ($variantId === null) {
                    $q->whereNull('variant_id');
                } else {
                    $q->where('variant_id', $variantId);
                }
            })
            ->where('date', $date)
            ->increment('bounces');
    }
}
