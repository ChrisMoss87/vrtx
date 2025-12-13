<?php

namespace App\Services\LandingPage;

use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Models\LandingPageVariant;
use App\Models\LandingPageAnalytics;
use App\Models\LandingPageVisit;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class LandingPageService
{
    public function getPages(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = LandingPage::with(['template', 'webForm', 'campaign', 'creator']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $query->orderBy($sortField, $sortOrder)->paginate($perPage);
    }

    public function createPage(array $data, int $createdBy): LandingPage
    {
        $slug = $data['slug'] ?? Str::slug($data['name']);
        $slug = $this->ensureUniqueSlug($slug);

        $content = $data['content'] ?? [];
        $styles = $data['styles'] ?? [];

        // If using a template, copy its content
        if (!empty($data['template_id'])) {
            $template = LandingPageTemplate::find($data['template_id']);
            if ($template) {
                $content = $template->content;
                $styles = $template->styles;
                $template->incrementUsage();
            }
        }

        return LandingPage::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'status' => 'draft',
            'template_id' => $data['template_id'] ?? null,
            'content' => $content,
            'settings' => $data['settings'] ?? [],
            'seo_settings' => $data['seo_settings'] ?? [],
            'styles' => $styles,
            'web_form_id' => $data['web_form_id'] ?? null,
            'thank_you_page_type' => $data['thank_you_page_type'] ?? 'message',
            'thank_you_message' => $data['thank_you_message'] ?? 'Thank you for your submission!',
            'thank_you_redirect_url' => $data['thank_you_redirect_url'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'created_by' => $createdBy,
        ]);
    }

    public function updatePage(LandingPage $page, array $data): LandingPage
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['slug']) && $data['slug'] !== $page->slug) {
            $updateData['slug'] = $this->ensureUniqueSlug($data['slug'], $page->id);
        }

        $simpleFields = [
            'description', 'content', 'settings', 'seo_settings', 'styles',
            'web_form_id', 'thank_you_page_type', 'thank_you_message',
            'thank_you_redirect_url', 'thank_you_page_id', 'campaign_id',
            'favicon_url', 'og_image_url', 'custom_domain',
        ];

        foreach ($simpleFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        $page->update($updateData);

        return $page->fresh();
    }

    protected function ensureUniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = LandingPage::where('slug', $slug);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if (!$query->exists()) {
                break;
            }

            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }

    public function duplicatePage(LandingPage $page, int $createdBy): LandingPage
    {
        $newSlug = $this->ensureUniqueSlug($page->slug . '-copy');

        $newPage = LandingPage::create([
            'name' => $page->name . ' (Copy)',
            'slug' => $newSlug,
            'description' => $page->description,
            'status' => 'draft',
            'template_id' => $page->template_id,
            'content' => $page->content,
            'settings' => $page->settings,
            'seo_settings' => $page->seo_settings,
            'styles' => $page->styles,
            'web_form_id' => $page->web_form_id,
            'thank_you_page_type' => $page->thank_you_page_type,
            'thank_you_message' => $page->thank_you_message,
            'thank_you_redirect_url' => $page->thank_you_redirect_url,
            'campaign_id' => $page->campaign_id,
            'created_by' => $createdBy,
        ]);

        // Duplicate variants if A/B testing
        if ($page->is_ab_testing_enabled) {
            foreach ($page->variants as $variant) {
                $newPage->variants()->create([
                    'name' => $variant->name,
                    'variant_code' => $variant->variant_code,
                    'content' => $variant->content,
                    'styles' => $variant->styles,
                    'traffic_percentage' => $variant->traffic_percentage,
                    'is_active' => true,
                ]);
            }
            $newPage->update(['is_ab_testing_enabled' => true]);
        }

        return $newPage;
    }

    public function publishPage(LandingPage $page): LandingPage
    {
        $page->publish();
        return $page->fresh();
    }

    public function unpublishPage(LandingPage $page): LandingPage
    {
        $page->unpublish();
        return $page->fresh();
    }

    public function createVariant(LandingPage $page, array $data): LandingPageVariant
    {
        // Get next variant code
        $existingCodes = $page->variants()->pluck('variant_code')->toArray();
        $nextCode = 'A';
        while (in_array($nextCode, $existingCodes)) {
            $nextCode++;
        }

        $variant = $page->variants()->create([
            'name' => $data['name'] ?? "Variant {$nextCode}",
            'variant_code' => $data['variant_code'] ?? $nextCode,
            'content' => $data['content'] ?? $page->content,
            'styles' => $data['styles'] ?? $page->styles,
            'traffic_percentage' => $data['traffic_percentage'] ?? 50,
            'is_active' => true,
        ]);

        // Rebalance traffic percentages
        $this->rebalanceVariantTraffic($page);

        // Enable A/B testing if not already
        if (!$page->is_ab_testing_enabled) {
            $page->update(['is_ab_testing_enabled' => true]);
        }

        return $variant;
    }

    public function updateVariant(LandingPageVariant $variant, array $data): LandingPageVariant
    {
        $variant->update([
            'name' => $data['name'] ?? $variant->name,
            'content' => $data['content'] ?? $variant->content,
            'styles' => $data['styles'] ?? $variant->styles,
            'traffic_percentage' => $data['traffic_percentage'] ?? $variant->traffic_percentage,
            'is_active' => $data['is_active'] ?? $variant->is_active,
        ]);

        return $variant->fresh();
    }

    public function deleteVariant(LandingPageVariant $variant): void
    {
        $page = $variant->page;
        $variant->delete();

        // Rebalance remaining variants
        $this->rebalanceVariantTraffic($page);

        // Disable A/B testing if only one or no variants left
        if ($page->variants()->count() <= 1) {
            $page->update(['is_ab_testing_enabled' => false]);
        }
    }

    protected function rebalanceVariantTraffic(LandingPage $page): void
    {
        $variants = $page->variants()->where('is_active', true)->get();
        $count = $variants->count();

        if ($count === 0) {
            return;
        }

        $percentageEach = intval(100 / $count);
        $remainder = 100 - ($percentageEach * $count);

        foreach ($variants as $index => $variant) {
            $percentage = $percentageEach;
            if ($index === 0) {
                $percentage += $remainder;
            }
            $variant->update(['traffic_percentage' => $percentage]);
        }
    }

    public function recordVisit(LandingPage $page, array $data): LandingPageVisit
    {
        $userAgentData = LandingPageVisit::parseUserAgent($data['user_agent'] ?? '');

        $variant = null;
        if ($page->is_ab_testing_enabled) {
            $variant = $page->selectVariant($data['visitor_id'] ?? null);
        }

        $visit = LandingPageVisit::create([
            'page_id' => $page->id,
            'variant_id' => $variant?->id,
            'visitor_id' => $data['visitor_id'] ?? Str::uuid()->toString(),
            'session_id' => $data['session_id'] ?? Str::uuid()->toString(),
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
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

        // Record analytics
        LandingPageAnalytics::recordView($page->id, $variant?->id, [
            'referrer' => $data['referrer'] ?? null,
            'device_type' => $userAgentData['device_type'],
            'country' => $data['country'] ?? null,
        ]);

        return $visit;
    }

    public function getPageAnalytics(LandingPage $page, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = $page->analytics();

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $analytics = $query->orderBy('date')->get();

        // Calculate totals
        $totals = [
            'views' => $analytics->sum('views'),
            'unique_visitors' => $analytics->sum('unique_visitors'),
            'form_submissions' => $analytics->sum('form_submissions'),
            'bounces' => $analytics->sum('bounces'),
        ];

        $totals['conversion_rate'] = $totals['views'] > 0
            ? round(($totals['form_submissions'] / $totals['views']) * 100, 2)
            : 0;

        $totals['bounce_rate'] = $totals['views'] > 0
            ? round(($totals['bounces'] / $totals['views']) * 100, 2)
            : 0;

        // Aggregate breakdowns
        $referrerBreakdown = [];
        $deviceBreakdown = [];
        $locationBreakdown = [];

        foreach ($analytics as $day) {
            foreach ($day->referrer_breakdown ?? [] as $referrer => $count) {
                $referrerBreakdown[$referrer] = ($referrerBreakdown[$referrer] ?? 0) + $count;
            }
            foreach ($day->device_breakdown ?? [] as $device => $count) {
                $deviceBreakdown[$device] = ($deviceBreakdown[$device] ?? 0) + $count;
            }
            foreach ($day->location_breakdown ?? [] as $location => $count) {
                $locationBreakdown[$location] = ($locationBreakdown[$location] ?? 0) + $count;
            }
        }

        arsort($referrerBreakdown);
        arsort($deviceBreakdown);
        arsort($locationBreakdown);

        return [
            'totals' => $totals,
            'daily' => $analytics,
            'referrer_breakdown' => array_slice($referrerBreakdown, 0, 10, true),
            'device_breakdown' => $deviceBreakdown,
            'location_breakdown' => array_slice($locationBreakdown, 0, 10, true),
        ];
    }

    public function getVariantComparison(LandingPage $page): array
    {
        $variants = $page->variants()->with('analytics')->get();

        $comparison = [];

        foreach ($variants as $variant) {
            $views = $variant->getTotalViews();
            $conversions = $variant->getTotalConversions();

            $comparison[] = [
                'id' => $variant->id,
                'name' => $variant->name,
                'variant_code' => $variant->variant_code,
                'traffic_percentage' => $variant->traffic_percentage,
                'views' => $views,
                'conversions' => $conversions,
                'conversion_rate' => $variant->getConversionRate(),
                'is_winner' => $variant->is_winner,
                'is_active' => $variant->is_active,
            ];
        }

        return $comparison;
    }
}
