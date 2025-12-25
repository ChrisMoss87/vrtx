<?php

namespace App\Http\Controllers\Api\LandingPage;

use App\Domain\LandingPage\Repositories\LandingPageRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class LandingPageController extends Controller
{
    private const STATUSES = ['draft', 'published', 'archived'];
    private const THANK_YOU_TYPES = ['message', 'redirect', 'page'];
    private const TEMPLATE_CATEGORIES = ['general', 'business', 'ecommerce', 'education', 'event', 'nonprofit'];

    public function __construct(
        protected LandingPageRepositoryInterface $repository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'campaign_id', 'template_id', 'ab_testing', 'created_by', 'search']);
        $filters['sort_by'] = $request->input('sort_field', 'created_at');
        $filters['sort_dir'] = $request->input('sort_order', 'desc');
        $perPage = $request->integer('per_page', 20);
        $page = $request->integer('page', 1);

        $result = $this->repository->listPages($filters, $perPage, $page);

        return response()->json([
            'success' => true,
            'data' => $result->items,
            'meta' => [
                'current_page' => $result->currentPage,
                'last_page' => $result->lastPage,
                'per_page' => $result->perPage,
                'total' => $result->total,
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $page = $this->repository->getPageById($id, ['template', 'creator', 'campaign', 'variants']);

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Landing page not found',
            ], Response::HTTP_NOT_FOUND);
        }

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

        $validated['created_by'] = Auth::id();

        $page = $this->repository->createPage($validated);

        return response()->json([
            'success' => true,
            'message' => 'Landing page created successfully',
            'data' => $page,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id): JsonResponse
    {
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

        $page = $this->repository->updatePage($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Landing page updated successfully',
            'data' => $page,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $page = $this->repository->getPageById($id);

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Landing page not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($page['status'] === 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a published page. Please unpublish it first.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->repository->deletePage($id);

        return response()->json([
            'success' => true,
            'message' => 'Landing page deleted successfully',
        ]);
    }

    public function duplicate(int $id): JsonResponse
    {
        $page = $this->repository->getPageById($id);

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Landing page not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $newName = $page['name'] . ' (Copy)';
        $newPage = $this->repository->duplicatePage($id, $newName, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Landing page duplicated successfully',
            'data' => $newPage,
        ], Response::HTTP_CREATED);
    }

    public function publish(int $id): JsonResponse
    {
        $page = $this->repository->publishPage($id);

        return response()->json([
            'success' => true,
            'message' => 'Landing page published successfully',
            'data' => $page,
        ]);
    }

    public function unpublish(int $id): JsonResponse
    {
        $page = $this->repository->unpublishPage($id);

        return response()->json([
            'success' => true,
            'message' => 'Landing page unpublished successfully',
            'data' => $page,
        ]);
    }

    public function archive(int $id): JsonResponse
    {
        $page = $this->repository->archivePage($id);

        return response()->json([
            'success' => true,
            'message' => 'Landing page archived successfully',
            'data' => $page,
        ]);
    }

    public function analytics(Request $request, int $id): JsonResponse
    {
        $analytics = $this->repository->getPageAnalytics(
            $id,
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
            'data' => self::STATUSES,
        ]);
    }

    public function thankYouTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => self::THANK_YOU_TYPES,
        ]);
    }

    // ===== VARIANT ENDPOINTS =====

    public function variants(int $id): JsonResponse
    {
        $comparison = $this->repository->getVariantAnalytics($id);

        return response()->json([
            'success' => true,
            'data' => $comparison,
        ]);
    }

    public function createVariant(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'content' => 'nullable|array',
            'styles' => 'nullable|array',
            'traffic_percentage' => 'nullable|integer|min:1|max:100',
        ]);

        $variant = $this->repository->createVariant($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Variant created successfully',
            'data' => $variant,
        ], Response::HTTP_CREATED);
    }

    public function updateVariant(Request $request, int $id, int $variantId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'content' => 'nullable|array',
            'styles' => 'nullable|array',
            'traffic_percentage' => 'nullable|integer|min:1|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $variant = $this->repository->updateVariant($variantId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Variant updated successfully',
            'data' => $variant,
        ]);
    }

    public function deleteVariant(int $id, int $variantId): JsonResponse
    {
        $variant = $this->repository->getVariantById($variantId);

        if (!$variant) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($variant['is_winner']) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the winning variant',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->repository->deleteVariant($variantId);

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted successfully',
        ]);
    }

    public function declareWinner(int $id, int $variantId): JsonResponse
    {
        $variant = $this->repository->declareVariantWinner($variantId);

        return response()->json([
            'success' => true,
            'message' => 'Variant declared as winner',
            'data' => $variant,
        ]);
    }

    // ===== TEMPLATE ENDPOINTS =====

    public function templates(Request $request): JsonResponse
    {
        $filters = [];

        if ($category = $request->query('category')) {
            $filters['category'] = $category;
        }

        if ($search = $request->query('search')) {
            $filters['search'] = $search;
        }

        $templates = $this->repository->listTemplates($filters);

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function showTemplate(int $templateId): JsonResponse
    {
        $template = $this->repository->getTemplateById($templateId);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], Response::HTTP_NOT_FOUND);
        }

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

        $validated['created_by'] = Auth::id();

        $template = $this->repository->createTemplate($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully',
            'data' => $template,
        ], Response::HTTP_CREATED);
    }

    public function updateTemplate(Request $request, int $templateId): JsonResponse
    {
        $template = $this->repository->getTemplateById($templateId);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($template['is_system']) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update system templates',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'thumbnail_url' => 'nullable|url',
            'content' => 'sometimes|array',
            'styles' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $template = $this->repository->updateTemplate($templateId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully',
            'data' => $template,
        ]);
    }

    public function destroyTemplate(int $templateId): JsonResponse
    {
        $template = $this->repository->getTemplateById($templateId);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($template['is_system']) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete system templates',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->repository->deleteTemplate($templateId);

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully',
        ]);
    }

    public function templateCategories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => self::TEMPLATE_CATEGORIES,
        ]);
    }

    public function saveAsTemplate(Request $request, int $id): JsonResponse
    {
        $page = $this->repository->getPageById($id);

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Landing page not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        $templateData = [
            'name' => $validated['name'],
            'category' => $validated['category'] ?? 'general',
            'description' => $validated['description'] ?? null,
            'content' => $page['content'],
            'styles' => $page['styles'],
            'created_by' => Auth::id(),
        ];

        $template = $this->repository->createTemplate($templateData);

        return response()->json([
            'success' => true,
            'message' => 'Page saved as template',
            'data' => $template,
        ], Response::HTTP_CREATED);
    }
}
