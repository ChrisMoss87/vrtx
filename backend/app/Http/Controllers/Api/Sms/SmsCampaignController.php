<?php

namespace App\Http\Controllers\Api\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsCampaign;
use App\Services\Sms\SmsCampaignService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SmsCampaignController extends Controller
{
    protected SmsCampaignService $campaignService;

    public function __construct(SmsCampaignService $campaignService)
    {
        $this->campaignService = $campaignService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = SmsCampaign::with(['connection:id,name', 'template:id,name', 'creator:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $campaigns = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($campaigns);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'connection_id' => 'required|exists:sms_connections,id',
            'template_id' => 'nullable|exists:sms_templates,id',
            'message_content' => 'required_without:template_id|nullable|string|max:1600',
            'target_module' => 'nullable|string',
            'target_filters' => 'nullable|array',
            'phone_field' => 'nullable|string',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $campaign = $this->campaignService->create($validated);

        return response()->json(['data' => $campaign], 201);
    }

    public function show(SmsCampaign $smsCampaign): JsonResponse
    {
        $smsCampaign->load(['connection:id,name,phone_number', 'template:id,name', 'creator:id,name']);

        return response()->json([
            'data' => $smsCampaign,
            'stats' => $this->campaignService->getStats($smsCampaign),
        ]);
    }

    public function update(Request $request, SmsCampaign $smsCampaign): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'connection_id' => 'sometimes|exists:sms_connections,id',
            'template_id' => 'nullable|exists:sms_templates,id',
            'message_content' => 'nullable|string|max:1600',
            'target_module' => 'nullable|string',
            'target_filters' => 'nullable|array',
            'phone_field' => 'nullable|string',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $campaign = $this->campaignService->update($smsCampaign, $validated);

        return response()->json(['data' => $campaign]);
    }

    public function destroy(SmsCampaign $smsCampaign): JsonResponse
    {
        if (!$smsCampaign->canEdit()) {
            return response()->json(['message' => 'Cannot delete campaign in current status'], 403);
        }

        $smsCampaign->delete();

        return response()->json(null, 204);
    }

    public function schedule(Request $request, SmsCampaign $smsCampaign): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        try {
            $campaign = $this->campaignService->schedule(
                $smsCampaign,
                new \DateTime($validated['scheduled_at'])
            );

            return response()->json(['data' => $campaign]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function sendNow(SmsCampaign $smsCampaign): JsonResponse
    {
        try {
            $campaign = $this->campaignService->sendNow($smsCampaign);

            return response()->json(['data' => $campaign]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function pause(SmsCampaign $smsCampaign): JsonResponse
    {
        if (!$smsCampaign->isSending()) {
            return response()->json(['message' => 'Campaign is not currently sending'], 400);
        }

        $smsCampaign->pause();

        return response()->json(['data' => $smsCampaign->refresh()]);
    }

    public function cancel(SmsCampaign $smsCampaign): JsonResponse
    {
        if (!$smsCampaign->canCancel()) {
            return response()->json(['message' => 'Campaign cannot be cancelled'], 400);
        }

        $smsCampaign->cancel();

        return response()->json(['data' => $smsCampaign->refresh()]);
    }

    public function preview(Request $request, SmsCampaign $smsCampaign): JsonResponse
    {
        $sampleData = $request->input('sample_data');

        return response()->json([
            'data' => $this->campaignService->preview($smsCampaign, $sampleData),
        ]);
    }

    public function recipients(SmsCampaign $smsCampaign): JsonResponse
    {
        $recipients = $this->campaignService->getRecipients($smsCampaign);

        return response()->json([
            'data' => [
                'count' => $recipients->count(),
                'sample' => $recipients->take(10)->map(function ($record) use ($smsCampaign) {
                    return [
                        'id' => $record->id,
                        'name' => $record->data['name'] ?? $record->data['first_name'] ?? 'Unknown',
                        'phone' => $record->data[$smsCampaign->phone_field] ?? null,
                    ];
                }),
            ],
        ]);
    }

    public function stats(SmsCampaign $smsCampaign): JsonResponse
    {
        return response()->json([
            'data' => $this->campaignService->getStats($smsCampaign),
        ]);
    }
}
