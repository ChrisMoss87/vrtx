<?php

namespace App\Http\Controllers\Api\Sms;

use App\Application\Services\Sms\SmsApplicationService;
use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Sms\SmsCampaignService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SmsCampaignController extends Controller
{
    public function __construct(
        protected SmsApplicationService $smsApplicationService,
        protected SmsCampaignService $campaignService,
        protected SmsMessageRepositoryInterface $messageRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->filled('status')) {
            $filters['status'] = $request->status;
        }

        if ($request->filled('connection_id')) {
            $filters['connection_id'] = $request->connection_id;
        }

        if ($request->filled('search')) {
            $filters['search'] = $request->search;
        }

        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);

        $result = $this->messageRepository->listCampaigns($filters, $perPage, $page);

        return response()->json([
            'data' => $result->items(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'total' => $result->total(),
            'last_page' => $result->lastPage(),
        ]);
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

    public function show(int $id): JsonResponse
    {
        $campaign = $this->messageRepository->findCampaignById($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        $stats = $this->messageRepository->getCampaignStats($id);

        return response()->json([
            'data' => $campaign,
            'stats' => $stats,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $campaign = $this->messageRepository->findCampaignById($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        // Check if campaign can be edited
        if (!in_array($campaign['status'], ['draft', 'scheduled'])) {
            return response()->json(['message' => 'Cannot edit campaign in current status'], 403);
        }

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

        $updated = $this->messageRepository->updateCampaign($id, $validated);

        return response()->json(['data' => $updated]);
    }

    public function destroy(int $id): JsonResponse
    {
        $campaign = $this->messageRepository->findCampaignById($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        // Check if campaign can be edited (deleted)
        if (!in_array($campaign['status'], ['draft', 'scheduled'])) {
            return response()->json(['message' => 'Cannot delete campaign in current status'], 403);
        }

        DB::table('sms_campaigns')->where('id', $id)->delete();

        return response()->json(null, 204);
    }

    public function schedule(Request $request, int $id): JsonResponse
    {
        $campaign = $this->messageRepository->findCampaignById($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        try {
            $updated = $this->campaignService->schedule(
                (object) $campaign,
                new \DateTime($validated['scheduled_at'])
            );

            return response()->json(['data' => $updated]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function sendNow(int $id): JsonResponse
    {
        $campaign = $this->messageRepository->findCampaignById($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        try {
            $updated = $this->campaignService->sendNow((object) $campaign);

            return response()->json(['data' => $updated]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function pause(int $id): JsonResponse
    {
        $campaign = $this->messageRepository->findCampaignById($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        if ($campaign['status'] !== 'sending') {
            return response()->json(['message' => 'Campaign is not currently sending'], 400);
        }

        $updated = $this->messageRepository->updateCampaign($id, ['status' => 'paused']);

        return response()->json(['data' => $updated]);
    }

    public function cancel(int $id): JsonResponse
    {
        $campaign = $this->messageRepository->findCampaignById($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        if (!in_array($campaign['status'], ['scheduled', 'sending'])) {
            return response()->json(['message' => 'Campaign cannot be cancelled'], 400);
        }

        $updated = $this->messageRepository->updateCampaign($id, ['status' => 'cancelled']);

        return response()->json(['data' => $updated]);
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        $campaign = $this->messageRepository->findCampaignById($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        $sampleData = $request->input('sample_data');

        return response()->json([
            'data' => $this->campaignService->preview((object) $campaign, $sampleData),
        ]);
    }

    public function recipients(int $id): JsonResponse
    {
        $campaign = $this->messageRepository->findCampaignById($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        $recipients = $this->campaignService->getRecipients((object) $campaign);

        return response()->json([
            'data' => [
                'count' => $recipients->count(),
                'sample' => $recipients->take(10)->map(function ($record) use ($campaign) {
                    return [
                        'id' => $record->id,
                        'name' => $record->data['name'] ?? $record->data['first_name'] ?? 'Unknown',
                        'phone' => $record->data[$campaign['phone_field']] ?? null,
                    ];
                }),
            ],
        ]);
    }

    public function stats(int $id): JsonResponse
    {
        $stats = $this->messageRepository->getCampaignStats($id);

        return response()->json([
            'data' => $stats,
        ]);
    }
}
