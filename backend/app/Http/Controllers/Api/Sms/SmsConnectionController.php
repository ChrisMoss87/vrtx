<?php

namespace App\Http\Controllers\Api\Sms;

use App\Application\Services\Sms\SmsApplicationService;
use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Sms\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SmsConnectionController extends Controller
{
    public function __construct(
        protected SmsApplicationService $smsApplicationService,
        protected SmsMessageRepositoryInterface $messageRepository
    ) {}

    public function index(): JsonResponse
    {
        $connections = $this->messageRepository->listConnections();

        // Get message and campaign counts for each connection
        foreach ($connections as &$connection) {
            $connection['messages_count'] = DB::table('sms_messages')
                ->where('connection_id', $connection['id'])
                ->count();
            $connection['campaigns_count'] = DB::table('sms_campaigns')
                ->where('connection_id', $connection['id'])
                ->count();
        }

        // Sort by created_at desc
        usort($connections, function ($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });

        return response()->json(['data' => $connections]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|in:twilio,vonage,messagebird,plivo',
            'phone_number' => 'required|string|max:20',
            'account_sid' => 'required|string|max:255',
            'auth_token' => 'required|string',
            'messaging_service_sid' => 'nullable|string|max:255',
            'capabilities' => 'nullable|array',
            'settings' => 'nullable|array',
            'daily_limit' => 'nullable|integer|min:1',
            'monthly_limit' => 'nullable|integer|min:1',
        ]);

        $connection = $this->messageRepository->createConnection($validated);

        return response()->json(['data' => $connection], 201);
    }

    public function show(int $id): JsonResponse
    {
        $connection = $this->messageRepository->findConnectionById($id);

        if (!$connection) {
            return response()->json(['message' => 'Connection not found'], 404);
        }

        // Get message and campaign counts
        $connection['messages_count'] = DB::table('sms_messages')
            ->where('connection_id', $id)
            ->count();
        $connection['campaigns_count'] = DB::table('sms_campaigns')
            ->where('connection_id', $id)
            ->count();

        return response()->json(['data' => $connection]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'provider' => 'sometimes|in:twilio,vonage,messagebird,plivo',
            'phone_number' => 'sometimes|string|max:20',
            'account_sid' => 'sometimes|string|max:255',
            'auth_token' => 'sometimes|string',
            'messaging_service_sid' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'capabilities' => 'nullable|array',
            'settings' => 'nullable|array',
            'daily_limit' => 'nullable|integer|min:1',
            'monthly_limit' => 'nullable|integer|min:1',
        ]);

        $connection = $this->messageRepository->updateConnection($id, $validated);

        if (!$connection) {
            return response()->json(['message' => 'Connection not found'], 404);
        }

        return response()->json(['data' => $connection]);
    }

    public function destroy(int $id): JsonResponse
    {
        $result = $this->messageRepository->deleteConnection($id);

        if (!$result) {
            return response()->json(['message' => 'Connection not found'], 404);
        }

        return response()->json(null, 204);
    }

    public function verify(int $id): JsonResponse
    {
        $connection = $this->messageRepository->findConnectionById($id);

        if (!$connection) {
            return response()->json(['message' => 'Connection not found'], 404);
        }

        if ($connection['provider'] !== 'twilio') {
            return response()->json([
                'success' => false,
                'message' => 'Verification only supported for Twilio connections',
            ], 400);
        }

        $service = new TwilioService((object) $connection);
        $result = $service->verifyCredentials();

        if ($result['success']) {
            $connection = $this->messageRepository->updateConnection($id, ['is_verified' => true]);
        }

        return response()->json([
            'data' => $connection,
            'verification' => $result,
        ]);
    }

    public function stats(int $id): JsonResponse
    {
        $usage = $this->messageRepository->getConnectionUsage($id);

        return response()->json(['data' => $usage]);
    }
}
