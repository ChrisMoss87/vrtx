<?php

declare(strict_types=1);

namespace Plugins\SMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\SMS\Application\Services\SMSApplicationService;

class SMSCampaignController extends Controller
{
    public function __construct(
        private readonly SMSApplicationService $smsService,
    ) {}

    /**
     * List SMS campaigns.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status']);
        $perPage = $request->input('per_page', 20);

        $campaigns = $this->smsService->listCampaigns($filters, $perPage);

        return response()->json($campaigns);
    }

    /**
     * Create a new campaign.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'connection_id' => 'required|integer|exists:sms_connections,id',
            'template_id' => 'nullable|integer|exists:sms_templates,id',
            'content' => 'required_without:template_id|nullable|string|max:1600',
            'recipient_list' => 'required|array|min:1',
            'recipient_list.*.phone' => 'required|string|max:50',
            'recipient_list.*.data' => 'nullable|array',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $campaign = $this->smsService->createCampaign($validated);

        return response()->json(['data' => $campaign], 201);
    }

    /**
     * Get a specific campaign.
     */
    public function show(int $campaign): JsonResponse
    {
        $data = $this->smsService->getCampaign($campaign);

        if (!$data) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Update a campaign.
     */
    public function update(Request $request, int $campaign): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'content' => 'sometimes|string|max:1600',
            'recipient_list' => 'sometimes|array|min:1',
            'scheduled_at' => 'sometimes|nullable|date|after:now',
        ]);

        // Only allow updates if campaign is in draft status
        $existing = $this->smsService->getCampaign($campaign);

        if (!$existing) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        if ($existing['status'] !== 'draft') {
            return response()->json(['message' => 'Can only update draft campaigns'], 400);
        }

        $data = app(\Plugins\SMS\Domain\Repositories\SMSRepositoryInterface::class)
            ->updateCampaign($campaign, $validated);

        return response()->json(['data' => $data]);
    }

    /**
     * Start a campaign.
     */
    public function start(int $campaign): JsonResponse
    {
        $data = $this->smsService->getCampaign($campaign);

        if (!$data) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        if ($data['status'] !== 'draft' && $data['status'] !== 'scheduled') {
            return response()->json(['message' => 'Campaign cannot be started'], 400);
        }

        // Start sending in background (would typically dispatch a job)
        // For now, update status
        $repository = app(\Plugins\SMS\Domain\Repositories\SMSRepositoryInterface::class);
        $repository->updateCampaign($campaign, [
            'status' => 'sending',
            'started_at' => now(),
        ]);

        return response()->json([
            'message' => 'Campaign started',
            'data' => $repository->findCampaignById($campaign),
        ]);
    }

    /**
     * Get campaign statistics.
     */
    public function stats(int $campaign): JsonResponse
    {
        $data = $this->smsService->getCampaign($campaign);

        if (!$data) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        $stats = $this->smsService->getCampaignStats($campaign);

        return response()->json(['data' => $stats]);
    }
}
