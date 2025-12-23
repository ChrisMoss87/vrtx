<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\LandingPage;

use App\Domain\LandingPage\Repositories\LandingPageRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Models\LandingPage;
use App\Models\LandingPageAnalytics;
use App\Models\LandingPageTemplate;
use App\Models\LandingPageVariant;
use App\Models\LandingPageVisit;
use Illuminate\Support\Facades\DB;

class EloquentLandingPageRepository implements LandingPageRepositoryInterface
{
    // =========================================================================
    // QUERY METHODS - LANDING PAGES
    // =========================================================================

    public function listPages(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = LandingPage::query()
            ->with(['template:id,name', 'creator:id,name,email', 'campaign:id,name']);

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

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return PaginatedResult::create(
            items: $paginator->items() ? array_map(fn($item) => $item->toArray(), $paginator->items()) : [],
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage()
        );
    }

    public function getPageById(int $id, array $relations = []): ?array
    {
        $query = LandingPage::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        $page = $query->find($id);

        return $page ? $page->toArray() : null;
    }

    public function getPageBySlug(string $slug, array $relations = []): ?array
    {
        $query = LandingPage::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        $page = $query->where('slug', $slug)->first();

        return $page ? $page->toArray() : null;
    }

    public function getPublishedPages(array $relations = []): array
    {
        $query = LandingPage::published();

        if (!empty($relations)) {
            $query->with($relations);
        } else {
            $query->with(['template:id,name']);
        }

        return $query->orderBy('published_at', 'desc')
            ->get()
            ->map(fn($item) => $item->toArray())
            ->toArray();
    }

    public function getDraftPages(array $relations = []): array
    {
        $query = LandingPage::draft();

        if (!empty($relations)) {
            $query->with($relations);
        } else {
            $query->with(['creator:id,name']);
        }

        return $query->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn($item) => $item->toArray())
            ->toArray();
    }

    // =========================================================================
    // QUERY METHODS - TEMPLATES
    // =========================================================================

    public function listTemplates(array $filters = []): array
    {
        $query = LandingPageTemplate::active();

        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
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
            ->map(fn($item) => $item->toArray())
            ->toArray();
    }

    public function getTemplateById(int $id, array $relations = []): ?array
    {
        $query = LandingPageTemplate::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        $template = $query->find($id);

        return $template ? $template->toArray() : null;
    }

    // =========================================================================
    // QUERY METHODS - VARIANTS
    // =========================================================================

    public function getVariantsByPageId(int $pageId): array
    {
        return LandingPageVariant::where('page_id', $pageId)
            ->orderBy('variant_code')
            ->get()
            ->map(fn($item) => $item->toArray())
            ->toArray();
    }

    public function getVariantById(int $id, array $relations = []): ?array
    {
        $query = LandingPageVariant::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        $variant = $query->find($id);

        return $variant ? $variant->toArray() : null;
    }

    // =========================================================================
    // QUERY METHODS - ANALYTICS
    // =========================================================================

    public function getPageAnalytics(int $pageId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = LandingPageAnalytics::where('page_id', $pageId);

        if ($dateFrom) {
            $query->where('date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('date', '<=', $dateTo);
        }

        return $query->orderBy('date', 'desc')
            ->get()
            ->map(fn($item) => $item->toArray())
            ->toArray();
    }

    public function getVariantAnalytics(int $pageId): array
    {
        $page = LandingPage::findOrFail($pageId);
        $variants = $page->variants;

        $results = [];
        foreach ($variants as $variant) {
            $results[] = [
                'variant_id' => $variant->id,
                'variant_code' => $variant->variant_code,
                'name' => $variant->name,
                'is_active' => $variant->is_active,
                'is_winner' => $variant->is_winner,
                'traffic_percentage' => $variant->traffic_percentage,
                'views' => $variant->getTotalViews(),
                'conversions' => $variant->getTotalConversions(),
                'conversion_rate' => $variant->getConversionRate(),
            ];
        }

        return $results;
    }

    public function getPageSummary(int $pageId): array
    {
        $page = LandingPage::findOrFail($pageId);

        $totalViews = $page->getTotalViews();
        $totalConversions = $page->getTotalConversions();
        $conversionRate = $page->getConversionRate();

        $analytics = LandingPageAnalytics::where('page_id', $pageId)
            ->selectRaw('SUM(unique_visitors) as unique_visitors, SUM(bounces) as bounces')
            ->first();

        $bounceRate = $totalViews > 0
            ? round(($analytics->bounces / $totalViews) * 100, 2)
            : 0;

        $avgTimeOnPage = LandingPageVisit::where('page_id', $pageId)
            ->whereNotNull('time_on_page')
            ->avg('time_on_page') ?? 0;

        return [
            'page_id' => $pageId,
            'total_views' => $totalViews,
            'unique_visitors' => $analytics->unique_visitors ?? 0,
            'total_conversions' => $totalConversions,
            'conversion_rate' => $conversionRate,
            'bounce_rate' => $bounceRate,
            'avg_time_on_page' => round($avgTimeOnPage, 2),
        ];
    }

    public function getPageTimeSeries(int $pageId, int $days = 30): array
    {
        $data = LandingPageAnalytics::where('page_id', $pageId)
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date')
            ->get();

        return [
            'labels' => $data->pluck('date')->map(fn($d) => $d->format('Y-m-d'))->toArray(),
            'views' => $data->pluck('views')->toArray(),
            'conversions' => $data->pluck('form_submissions')->toArray(),
            'unique_visitors' => $data->pluck('unique_visitors')->toArray(),
        ];
    }

    public function getTopReferrers(int $pageId, int $limit = 10): array
    {
        $analytics = LandingPageAnalytics::where('page_id', $pageId)
            ->whereNotNull('referrer_breakdown')
            ->get();

        $referrers = [];
        foreach ($analytics as $record) {
            foreach ($record->referrer_breakdown ?? [] as $referrer => $count) {
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
        $analytics = LandingPageAnalytics::where('page_id', $pageId)
            ->whereNotNull('device_breakdown')
            ->get();

        $devices = [];
        foreach ($analytics as $record) {
            foreach ($record->device_breakdown ?? [] as $device => $count) {
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
        $analytics = LandingPageAnalytics::where('page_id', $pageId)
            ->whereNotNull('location_breakdown')
            ->get();

        $locations = [];
        foreach ($analytics as $record) {
            foreach ($record->location_breakdown ?? [] as $location => $count) {
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
        return LandingPageVisit::where('page_id', $pageId)
            ->with(['variant'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($item) => $item->toArray())
            ->toArray();
    }

    public function getConvertedVisits(int $pageId): array
    {
        return LandingPageVisit::where('page_id', $pageId)
            ->where('converted', true)
            ->with(['variant', 'submission'])
            ->orderBy('converted_at', 'desc')
            ->get()
            ->map(fn($item) => $item->toArray())
            ->toArray();
    }

    // =========================================================================
    // QUERY METHODS - REPORTING
    // =========================================================================

    public function getPerformanceOverview(array $filters = []): array
    {
        $query = LandingPage::query();

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
            $views = $page->getTotalViews();
            $conversions = $page->getTotalConversions();

            $totalViews += $views;
            $totalConversions += $conversions;

            $pageStats[] = [
                'id' => $page->id,
                'name' => $page->name,
                'status' => $page->status,
                'views' => $views,
                'conversions' => $conversions,
                'conversion_rate' => $page->getConversionRate(),
            ];
        }

        // Sort by conversion rate
        usort($pageStats, fn($a, $b) => $b['conversion_rate'] <=> $a['conversion_rate']);

        return [
            'total_pages' => $pages->count(),
            'total_views' => $totalViews,
            'total_conversions' => $totalConversions,
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
            $page = LandingPage::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? \Illuminate\Support\Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'template_id' => $data['template_id'] ?? null,
                'content' => $data['content'] ?? [],
                'settings' => $data['settings'] ?? [],
                'seo_settings' => $data['seo_settings'] ?? [],
                'styles' => $data['styles'] ?? [],
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
            ]);

            // Increment template usage if template was used
            if ($page->template_id) {
                $page->template->incrementUsage();
            }

            return $page->toArray();
        });
    }

    public function updatePage(int $id, array $data): array
    {
        $page = LandingPage::findOrFail($id);

        $page->update(array_filter([
            'name' => $data['name'] ?? null,
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? null,
            'content' => $data['content'] ?? null,
            'settings' => $data['settings'] ?? null,
            'seo_settings' => $data['seo_settings'] ?? null,
            'styles' => $data['styles'] ?? null,
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
        ], fn($value) => $value !== null));

        return $page->fresh()->toArray();
    }

    public function deletePage(int $id): bool
    {
        $page = LandingPage::findOrFail($id);
        return $page->delete();
    }

    public function publishPage(int $id): array
    {
        $page = LandingPage::findOrFail($id);
        $page->publish();
        return $page->fresh()->toArray();
    }

    public function unpublishPage(int $id): array
    {
        $page = LandingPage::findOrFail($id);
        $page->unpublish();
        return $page->fresh()->toArray();
    }

    public function archivePage(int $id): array
    {
        $page = LandingPage::findOrFail($id);
        $page->archive();
        return $page->fresh()->toArray();
    }

    public function duplicatePage(int $id, string $newName, int $userId): array
    {
        $original = LandingPage::findOrFail($id);

        return DB::transaction(function () use ($original, $newName, $userId) {
            $duplicate = $original->replicate();
            $duplicate->name = $newName;
            $duplicate->slug = \Illuminate\Support\Str::slug($newName);
            $duplicate->status = 'draft';
            $duplicate->published_at = null;
            $duplicate->created_by = $userId;
            $duplicate->save();

            // Duplicate variants if A/B testing is enabled
            if ($original->is_ab_testing_enabled) {
                foreach ($original->variants as $variant) {
                    $newVariant = $variant->replicate();
                    $newVariant->page_id = $duplicate->id;
                    $newVariant->is_winner = false;
                    $newVariant->declared_winner_at = null;
                    $newVariant->save();
                }
            }

            return $duplicate->toArray();
        });
    }

    // =========================================================================
    // COMMAND METHODS - TEMPLATES
    // =========================================================================

    public function createTemplate(array $data): array
    {
        $template = LandingPageTemplate::create([
            'name' => $data['name'],
            'category' => $data['category'] ?? 'general',
            'description' => $data['description'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'content' => $data['content'] ?? [],
            'styles' => $data['styles'] ?? [],
            'is_system' => $data['is_system'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $data['created_by'],
        ]);

        return $template->toArray();
    }

    public function updateTemplate(int $id, array $data): array
    {
        $template = LandingPageTemplate::findOrFail($id);

        $template->update(array_filter([
            'name' => $data['name'] ?? null,
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'content' => $data['content'] ?? null,
            'styles' => $data['styles'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn($value) => $value !== null));

        return $template->fresh()->toArray();
    }

    public function deleteTemplate(int $id): bool
    {
        $template = LandingPageTemplate::findOrFail($id);

        // Don't allow deleting system templates
        if ($template->is_system) {
            throw new \InvalidArgumentException('Cannot delete system templates');
        }

        return $template->delete();
    }

    // =========================================================================
    // COMMAND METHODS - VARIANTS
    // =========================================================================

    public function createVariant(int $pageId, array $data): array
    {
        $page = LandingPage::findOrFail($pageId);

        // Enable A/B testing on the page
        if (!$page->is_ab_testing_enabled) {
            $page->update(['is_ab_testing_enabled' => true]);
        }

        $variant = LandingPageVariant::create([
            'page_id' => $pageId,
            'name' => $data['name'],
            'variant_code' => $data['variant_code'] ?? strtoupper(substr(uniqid(), -3)),
            'content' => $data['content'] ?? $page->content,
            'styles' => $data['styles'] ?? $page->styles,
            'traffic_percentage' => $data['traffic_percentage'] ?? 50,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $variant->toArray();
    }

    public function updateVariant(int $id, array $data): array
    {
        $variant = LandingPageVariant::findOrFail($id);

        $variant->update(array_filter([
            'name' => $data['name'] ?? null,
            'content' => $data['content'] ?? null,
            'styles' => $data['styles'] ?? null,
            'traffic_percentage' => $data['traffic_percentage'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn($value) => $value !== null));

        return $variant->fresh()->toArray();
    }

    public function deleteVariant(int $id): bool
    {
        $variant = LandingPageVariant::findOrFail($id);
        return $variant->delete();
    }

    public function declareVariantWinner(int $variantId): array
    {
        return DB::transaction(function () use ($variantId) {
            $variant = LandingPageVariant::findOrFail($variantId);
            $variant->declareWinner();
            return $variant->fresh()->toArray();
        });
    }

    // =========================================================================
    // COMMAND METHODS - VISIT TRACKING
    // =========================================================================

    public function recordVisit(int $pageId, array $data): array
    {
        $page = LandingPage::findOrFail($pageId);

        // Parse user agent
        $userAgentData = LandingPageVisit::parseUserAgent($data['user_agent'] ?? '');

        // Create visit record
        $visit = LandingPageVisit::create([
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
        ]);

        // Record in analytics
        LandingPageAnalytics::recordView($pageId, $data['variant_id'] ?? null, [
            'referrer' => $data['referrer'] ?? null,
            'device_type' => $userAgentData['device_type'],
            'country' => $data['country'] ?? null,
        ]);

        return $visit->toArray();
    }

    public function updateVisitEngagement(int $visitId, int $timeOnPage, int $scrollDepth): array
    {
        $visit = LandingPageVisit::findOrFail($visitId);
        $visit->updateEngagement($timeOnPage, $scrollDepth);
        return $visit->fresh()->toArray();
    }

    public function markVisitConverted(int $visitId, int $submissionId): array
    {
        $visit = LandingPageVisit::findOrFail($visitId);
        $visit->markConverted($submissionId);
        return $visit->fresh()->toArray();
    }
}
