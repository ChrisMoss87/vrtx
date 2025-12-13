<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignAudience;
use App\Models\CampaignAsset;
use App\Models\EmailCampaignTemplate;
use App\Services\Campaign\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CampaignController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService
    ) {}

    /**
     * List all campaigns
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['type', 'status', 'search', 'owner_id', 'sort_field', 'sort_order']);
        $perPage = $request->integer('per_page', 20);

        $campaigns = $this->campaignService->getCampaigns($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $campaigns->items(),
            'meta' => [
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
                'per_page' => $campaigns->perPage(),
                'total' => $campaigns->total(),
            ],
        ]);
    }

    /**
     * Get a single campaign
     */
    public function show(int $id): JsonResponse
    {
        $campaign = Campaign::with(['module', 'owner', 'creator', 'audiences.module', 'assets'])
            ->findOrFail($id);

        $analytics = $this->campaignService->getCampaignAnalytics($campaign);

        return response()->json([
            'success' => true,
            'data' => $campaign,
            'analytics' => $analytics,
        ]);
    }

    /**
     * Create a new campaign
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:' . implode(',', array_keys(Campaign::getTypes())),
            'module_id' => 'nullable|integer|exists:modules,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'settings' => 'nullable|array',
            'goals' => 'nullable|array',
            'owner_id' => 'nullable|integer|exists:users,id',
        ]);

        $campaign = $this->campaignService->createCampaign($validated, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Campaign created successfully',
            'data' => $campaign->load(['module', 'owner', 'creator']),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a campaign
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|string|in:' . implode(',', array_keys(Campaign::getTypes())),
            'module_id' => 'nullable|integer|exists:modules,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'settings' => 'nullable|array',
            'goals' => 'nullable|array',
            'owner_id' => 'nullable|integer|exists:users,id',
        ]);

        $campaign = $this->campaignService->updateCampaign($campaign, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Campaign updated successfully',
            'data' => $campaign->load(['module', 'owner', 'creator', 'audiences', 'assets']),
        ]);
    }

    /**
     * Delete a campaign
     */
    public function destroy(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        if ($campaign->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an active campaign. Please pause or cancel it first.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campaign deleted successfully',
        ]);
    }

    /**
     * Duplicate a campaign
     */
    public function duplicate(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        $newCampaign = $this->campaignService->duplicateCampaign($campaign, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Campaign duplicated successfully',
            'data' => $newCampaign->load(['module', 'owner', 'creator', 'audiences', 'assets']),
        ], Response::HTTP_CREATED);
    }

    /**
     * Start a campaign
     */
    public function start(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        try {
            $campaign = $this->campaignService->startCampaign($campaign);

            return response()->json([
                'success' => true,
                'message' => 'Campaign started successfully',
                'data' => $campaign,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Pause a campaign
     */
    public function pause(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        try {
            $campaign = $this->campaignService->pauseCampaign($campaign);

            return response()->json([
                'success' => true,
                'message' => 'Campaign paused successfully',
                'data' => $campaign,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Complete a campaign
     */
    public function complete(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        $campaign = $this->campaignService->completeCampaign($campaign);

        return response()->json([
            'success' => true,
            'message' => 'Campaign marked as completed',
            'data' => $campaign,
        ]);
    }

    /**
     * Cancel a campaign
     */
    public function cancel(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        $campaign = $this->campaignService->cancelCampaign($campaign);

        return response()->json([
            'success' => true,
            'message' => 'Campaign cancelled',
            'data' => $campaign,
        ]);
    }

    /**
     * Get campaign analytics
     */
    public function analytics(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        $analytics = $this->campaignService->getCampaignAnalytics($campaign);
        $topLinks = $this->campaignService->getTopLinks($campaign);

        return response()->json([
            'success' => true,
            'analytics' => $analytics,
            'top_links' => $topLinks,
        ]);
    }

    /**
     * Get campaign metrics over time
     */
    public function metrics(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $metrics = $this->campaignService->getCampaignMetricsOverTime($campaign, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Get campaign types
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Campaign::getTypes(),
        ]);
    }

    /**
     * Get campaign statuses
     */
    public function statuses(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Campaign::getStatuses(),
        ]);
    }

    // ===== AUDIENCE ENDPOINTS =====

    /**
     * Add audience to campaign
     */
    public function addAudience(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'module_id' => 'required|integer|exists:modules,id',
            'segment_rules' => 'nullable|array',
            'is_dynamic' => 'nullable|boolean',
        ]);

        $audience = $this->campaignService->addAudience($campaign, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Audience added successfully',
            'data' => $audience->load('module'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update audience
     */
    public function updateAudience(Request $request, int $id, int $audienceId): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        $audience = CampaignAudience::where('campaign_id', $campaign->id)
            ->findOrFail($audienceId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'segment_rules' => 'nullable|array',
            'is_dynamic' => 'nullable|boolean',
        ]);

        $audience = $this->campaignService->updateAudience($audience, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Audience updated successfully',
            'data' => $audience->load('module'),
        ]);
    }

    /**
     * Delete audience
     */
    public function deleteAudience(int $id, int $audienceId): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        $audience = CampaignAudience::where('campaign_id', $campaign->id)
            ->findOrFail($audienceId);

        $audience->delete();

        return response()->json([
            'success' => true,
            'message' => 'Audience deleted successfully',
        ]);
    }

    /**
     * Preview audience records
     */
    public function previewAudience(int $id, int $audienceId): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        $audience = CampaignAudience::where('campaign_id', $campaign->id)
            ->findOrFail($audienceId);

        $records = $this->campaignService->previewAudience($audience, 20);

        return response()->json([
            'success' => true,
            'data' => $records,
            'total_count' => $audience->contact_count,
        ]);
    }

    /**
     * Refresh audience count
     */
    public function refreshAudience(int $id, int $audienceId): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        $audience = CampaignAudience::where('campaign_id', $campaign->id)
            ->findOrFail($audienceId);

        $count = $audience->refreshCount();

        return response()->json([
            'success' => true,
            'message' => 'Audience refreshed',
            'contact_count' => $count,
        ]);
    }

    // ===== ASSET ENDPOINTS =====

    /**
     * Add asset to campaign
     */
    public function addAsset(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        $validated = $request->validate([
            'type' => 'required|string|in:email,image,document,landing_page',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $asset = $this->campaignService->addAsset($campaign, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Asset added successfully',
            'data' => $asset,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update asset
     */
    public function updateAsset(Request $request, int $id, int $assetId): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        $asset = CampaignAsset::where('campaign_id', $campaign->id)
            ->findOrFail($assetId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $asset = $this->campaignService->updateAsset($asset, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Asset updated successfully',
            'data' => $asset,
        ]);
    }

    /**
     * Delete asset
     */
    public function deleteAsset(int $id, int $assetId): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        $asset = CampaignAsset::where('campaign_id', $campaign->id)
            ->findOrFail($assetId);

        $asset->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asset deleted successfully',
        ]);
    }

    // ===== TEMPLATE ENDPOINTS =====

    /**
     * List email templates
     */
    public function templates(Request $request): JsonResponse
    {
        $query = EmailCampaignTemplate::active();

        if ($category = $request->query('category')) {
            $query->byCategory($category);
        }

        $templates = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Get a single template
     */
    public function showTemplate(int $templateId): JsonResponse
    {
        $template = EmailCampaignTemplate::findOrFail($templateId);

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * Create a template
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'subject' => 'nullable|string|max:255',
            'html_content' => 'required|string',
            'text_content' => 'nullable|string',
            'variables' => 'nullable|array',
        ]);

        $template = EmailCampaignTemplate::create([
            ...$validated,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully',
            'data' => $template,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a template
     */
    public function updateTemplate(Request $request, int $templateId): JsonResponse
    {
        $template = EmailCampaignTemplate::where('is_system', false)
            ->findOrFail($templateId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'subject' => 'nullable|string|max:255',
            'html_content' => 'sometimes|string',
            'text_content' => 'nullable|string',
            'variables' => 'nullable|array',
        ]);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully',
            'data' => $template->fresh(),
        ]);
    }

    /**
     * Delete a template
     */
    public function destroyTemplate(int $templateId): JsonResponse
    {
        $template = EmailCampaignTemplate::where('is_system', false)
            ->findOrFail($templateId);

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully',
        ]);
    }
}
