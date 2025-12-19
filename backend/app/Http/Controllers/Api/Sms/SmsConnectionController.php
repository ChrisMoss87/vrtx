<?php

namespace App\Http\Controllers\Api\Sms;

use App\Application\Services\Sms\SmsApplicationService;
use App\Http\Controllers\Controller;
use App\Models\SmsConnection;
use App\Services\Sms\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SmsConnectionController extends Controller
{
    public function __construct(
        protected SmsApplicationService $smsApplicationService
    ) {}
    public function index(): JsonResponse
    {
        $connections = SmsConnection::withCount(['messages', 'campaigns'])
            ->orderBy('created_at', 'desc')
            ->get();

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

        $connection = SmsConnection::create($validated);

        return response()->json(['data' => $connection], 201);
    }

    public function show(SmsConnection $smsConnection): JsonResponse
    {
        $smsConnection->loadCount(['messages', 'campaigns']);

        return response()->json(['data' => $smsConnection]);
    }

    public function update(Request $request, SmsConnection $smsConnection): JsonResponse
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

        $smsConnection->update($validated);

        return response()->json(['data' => $smsConnection]);
    }

    public function destroy(SmsConnection $smsConnection): JsonResponse
    {
        $smsConnection->delete();

        return response()->json(null, 204);
    }

    public function verify(SmsConnection $smsConnection): JsonResponse
    {
        if ($smsConnection->provider !== 'twilio') {
            return response()->json([
                'success' => false,
                'message' => 'Verification only supported for Twilio connections',
            ], 400);
        }

        $service = new TwilioService($smsConnection);
        $result = $service->verifyCredentials();

        if ($result['success']) {
            $smsConnection->update(['is_verified' => true]);
        }

        return response()->json([
            'data' => $smsConnection->refresh(),
            'verification' => $result,
        ]);
    }

    public function stats(SmsConnection $smsConnection): JsonResponse
    {
        $stats = [
            'today_count' => $smsConnection->getTodayMessageCount(),
            'month_count' => $smsConnection->getMonthMessageCount(),
            'daily_limit' => $smsConnection->daily_limit,
            'monthly_limit' => $smsConnection->monthly_limit,
            'daily_remaining' => max(0, $smsConnection->daily_limit - $smsConnection->getTodayMessageCount()),
            'monthly_remaining' => max(0, $smsConnection->monthly_limit - $smsConnection->getMonthMessageCount()),
        ];

        return response()->json(['data' => $stats]);
    }
}
