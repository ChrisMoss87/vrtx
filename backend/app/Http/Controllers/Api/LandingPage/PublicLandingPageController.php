<?php

namespace App\Http\Controllers\Api\LandingPage;

use App\Application\Services\LandingPage\LandingPageApplicationService;
use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\LandingPageVisit;
use App\Services\LandingPage\LandingPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicLandingPageController extends Controller
{
    public function __construct(
        protected LandingPageService $landingPageService,
        protected LandingPageApplicationService $landingPageApplicationService
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $page = LandingPage::where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Page not found',
            ], 404);
        }

        // Record visit
        $visitData = [
            'visitor_id' => $request->cookie('vrtx_visitor_id') ?? $request->input('visitor_id'),
            'session_id' => $request->session()->getId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->headers->get('referer'),
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'utm_term' => $request->query('utm_term'),
            'utm_content' => $request->query('utm_content'),
        ];

        $visit = $this->landingPageService->recordVisit($page, $visitData);

        // Get the appropriate content (variant or main)
        $content = $page->content;
        $styles = $page->styles;
        $variantId = null;

        if ($page->is_ab_testing_enabled && $visit->variant_id) {
            $variant = $page->variants()->find($visit->variant_id);
            if ($variant) {
                $content = $variant->content;
                $styles = $variant->styles;
                $variantId = $variant->id;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $page->id,
                'name' => $page->name,
                'slug' => $page->slug,
                'content' => $content,
                'styles' => $styles,
                'settings' => $page->settings,
                'seo_settings' => $page->seo_settings,
                'web_form_id' => $page->web_form_id,
                'thank_you_page_type' => $page->thank_you_page_type,
                'thank_you_message' => $page->thank_you_message,
                'thank_you_redirect_url' => $page->thank_you_redirect_url,
                'favicon_url' => $page->favicon_url,
                'og_image_url' => $page->og_image_url,
            ],
            'visit_id' => $visit->id,
            'variant_id' => $variantId,
        ]);
    }

    public function trackEngagement(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'visit_id' => 'required|integer',
            'time_on_page' => 'required|integer|min:0',
            'scroll_depth' => 'required|integer|min:0|max:100',
        ]);

        $visit = LandingPageVisit::find($validated['visit_id']);

        if (!$visit || $visit->page->slug !== $slug) {
            return response()->json([
                'success' => false,
                'message' => 'Visit not found',
            ], 404);
        }

        $visit->updateEngagement($validated['time_on_page'], $validated['scroll_depth']);

        return response()->json([
            'success' => true,
            'message' => 'Engagement tracked',
        ]);
    }

    public function trackConversion(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'visit_id' => 'required|integer',
            'submission_id' => 'required|integer',
        ]);

        $visit = LandingPageVisit::find($validated['visit_id']);

        if (!$visit || $visit->page->slug !== $slug) {
            return response()->json([
                'success' => false,
                'message' => 'Visit not found',
            ], 404);
        }

        $visit->markConverted($validated['submission_id']);

        return response()->json([
            'success' => true,
            'message' => 'Conversion tracked',
        ]);
    }

    public function thankYou(string $slug): JsonResponse
    {
        $page = LandingPage::where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Page not found',
            ], 404);
        }

        $thankYouData = [
            'type' => $page->thank_you_page_type,
            'message' => $page->thank_you_message,
            'redirect_url' => $page->thank_you_redirect_url,
        ];

        if ($page->thank_you_page_type === 'page' && $page->thank_you_page_id) {
            $thankYouPage = LandingPage::find($page->thank_you_page_id);
            if ($thankYouPage) {
                $thankYouData['page'] = [
                    'slug' => $thankYouPage->slug,
                    'content' => $thankYouPage->content,
                    'styles' => $thankYouPage->styles,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $thankYouData,
        ]);
    }
}
