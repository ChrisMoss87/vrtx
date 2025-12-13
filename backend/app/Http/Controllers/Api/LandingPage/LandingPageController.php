<?php

namespace App\Http\Controllers\Api\LandingPage;

use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Models\LandingPageVariant;
use App\Services\LandingPage\LandingPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class LandingPageController extends Controller
{
    public function __construct(
        protected LandingPageService $landingPageService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'campaign_id', 'search', 'sort_field', 'sort_order']);
        $perPage = $request->integer('per_page', 20);

        $pages = $this->landingPageService->getPages($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $pages->items(),
            'meta' => [
                'current_page' => $pages->currentPage(),
                'last_page' => $pages->lastPage(),
                'per_page' => $pages->perPage(),
                'total' => $pages->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $page = LandingPage::with(['template', 'webForm', 'campaign', 'creator', 'variants'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $page,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|regex:/^[a-z0-9-]+$/',
            'description' => 'nullable|string',
            'template_id' => 'nullable|integer|exists:landing_page_templates,id',
            'content' => 'nullable|array',
            'settings' => 'nullable|array',
            'seo_settings' => 'nullable|array',
            'styles' => 'nullable|array',
            'web_form_id' => 'nullable|integer|exists:web_forms,id',
            'thank_you_page_type' => 'nullable|string|in:message,redirect,page',
            'thank_you_message' => 'nullable|string',
            'thank_you_redirect_url' => 'nullable|url',
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
        ]);

        $page = $this->landingPageService->createPage($validated, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Landing page created successfully',
            'data' => $page->load(['template', 'webForm', 'campaign', 'creator']),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $page = LandingPage::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255|regex:/^[a-z0-9-]+$/',
            'description' => 'nullable|string',
            'content' => 'nullable|array',
            'settings' => 'nullable|array',
            'seo_settings' => 'nullable|array',
            'styles' => 'nullable|array',
            'web_form_id' => 'nullable|integer|exists:web_forms,id',
            'thank_you_page_type' => 'nullable|string|in:message,redirect,page',
            'thank_you_message' => 'nullable|string',
            'thank_you_redirect_url' => 'nullable|url',
            'thank_you_page_id' => 'nullable|integer|exists:landing_pages,id',
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
            'favicon_url' => 'nullable|url',
            'og_image_url' => 'nullable|url',
            'custom_domain' => 'nullable|string|max:255',
        ]);

        $page = $this->landingPageService->updatePage($page, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Landing page updated successfully',
            'data' => $page->load(['template', 'webForm', 'campaign', 'creator', 'variants']),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $page = LandingPage::findOrFail($id);

        if ($page->isPublished()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a published page. Please unpublish it first.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $page->delete();

        return response()->json([
            'success' => true,
            'message' => 'Landing page deleted successfully',
        ]);
    }

    public function duplicate(int $id): JsonResponse
    {
        $page = LandingPage::findOrFail($id);

        $newPage = $this->landingPageService->duplicatePage($page, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Landing page duplicated successfully',
            'data' => $newPage->load(['template', 'webForm', 'campaign', 'creator']),
        ], Response::HTTP_CREATED);
    }

    public function publish(int $id): JsonResponse
    {
        $page = LandingPage::findOrFail($id);

        $page = $this->landingPageService->publishPage($page);

        return response()->json([
            'success' => true,
            'message' => 'Landing page published successfully',
            'data' => $page,
        ]);
    }

    public function unpublish(int $id): JsonResponse
    {
        $page = LandingPage::findOrFail($id);

        $page = $this->landingPageService->unpublishPage($page);

        return response()->json([
            'success' => true,
            'message' => 'Landing page unpublished successfully',
            'data' => $page,
        ]);
    }

    public function archive(int $id): JsonResponse
    {
        $page = LandingPage::findOrFail($id);

        $page->archive();

        return response()->json([
            'success' => true,
            'message' => 'Landing page archived successfully',
            'data' => $page->fresh(),
        ]);
    }

    public function analytics(Request $request, int $id): JsonResponse
    {
        $page = LandingPage::findOrFail($id);

        $analytics = $this->landingPageService->getPageAnalytics(
            $page,
            $request->query('start_date'),
            $request->query('end_date')
        );

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    public function statuses(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LandingPage::getStatuses(),
        ]);
    }

    public function thankYouTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LandingPage::getThankYouTypes(),
        ]);
    }

    // ===== VARIANT ENDPOINTS =====

    public function variants(int $id): JsonResponse
    {
        $page = LandingPage::findOrFail($id);

        $comparison = $this->landingPageService->getVariantComparison($page);

        return response()->json([
            'success' => true,
            'data' => $comparison,
        ]);
    }

    public function createVariant(Request $request, int $id): JsonResponse
    {
        $page = LandingPage::findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'content' => 'nullable|array',
            'styles' => 'nullable|array',
            'traffic_percentage' => 'nullable|integer|min:1|max:100',
        ]);

        $variant = $this->landingPageService->createVariant($page, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Variant created successfully',
            'data' => $variant,
        ], Response::HTTP_CREATED);
    }

    public function updateVariant(Request $request, int $id, int $variantId): JsonResponse
    {
        $page = LandingPage::findOrFail($id);
        $variant = LandingPageVariant::where('page_id', $page->id)->findOrFail($variantId);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'content' => 'nullable|array',
            'styles' => 'nullable|array',
            'traffic_percentage' => 'nullable|integer|min:1|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $variant = $this->landingPageService->updateVariant($variant, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Variant updated successfully',
            'data' => $variant,
        ]);
    }

    public function deleteVariant(int $id, int $variantId): JsonResponse
    {
        $page = LandingPage::findOrFail($id);
        $variant = LandingPageVariant::where('page_id', $page->id)->findOrFail($variantId);

        if ($variant->is_winner) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the winning variant',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->landingPageService->deleteVariant($variant);

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted successfully',
        ]);
    }

    public function declareWinner(int $id, int $variantId): JsonResponse
    {
        $page = LandingPage::findOrFail($id);
        $variant = LandingPageVariant::where('page_id', $page->id)->findOrFail($variantId);

        $variant->declareWinner();

        return response()->json([
            'success' => true,
            'message' => 'Variant declared as winner',
            'data' => $page->fresh(['variants']),
        ]);
    }

    // ===== TEMPLATE ENDPOINTS =====

    public function templates(Request $request): JsonResponse
    {
        $query = LandingPageTemplate::active();

        if ($category = $request->query('category')) {
            $query->byCategory($category);
        }

        $templates = $query->orderBy('usage_count', 'desc')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function showTemplate(int $templateId): JsonResponse
    {
        $template = LandingPageTemplate::findOrFail($templateId);

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'thumbnail_url' => 'nullable|url',
            'content' => 'required|array',
            'styles' => 'nullable|array',
        ]);

        $template = LandingPageTemplate::create([
            ...$validated,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully',
            'data' => $template,
        ], Response::HTTP_CREATED);
    }

    public function updateTemplate(Request $request, int $templateId): JsonResponse
    {
        $template = LandingPageTemplate::where('is_system', false)->findOrFail($templateId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'thumbnail_url' => 'nullable|url',
            'content' => 'sometimes|array',
            'styles' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully',
            'data' => $template->fresh(),
        ]);
    }

    public function destroyTemplate(int $templateId): JsonResponse
    {
        $template = LandingPageTemplate::where('is_system', false)->findOrFail($templateId);

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully',
        ]);
    }

    public function templateCategories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LandingPageTemplate::getCategories(),
        ]);
    }

    public function saveAsTemplate(Request $request, int $id): JsonResponse
    {
        $page = LandingPage::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        $template = LandingPageTemplate::create([
            'name' => $validated['name'],
            'category' => $validated['category'] ?? 'general',
            'description' => $validated['description'] ?? null,
            'content' => $page->content,
            'styles' => $page->styles,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Page saved as template',
            'data' => $template,
        ], Response::HTTP_CREATED);
    }
}
