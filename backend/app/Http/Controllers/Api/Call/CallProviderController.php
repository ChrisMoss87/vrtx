<?php

namespace App\Http\Controllers\Api\Call;

use App\Application\Services\Call\CallApplicationService;
use App\Http\Controllers\Controller;
use App\Models\CallProvider;
use App\Services\Call\TwilioCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CallProviderController extends Controller
{
    public function __construct(
        protected CallApplicationService $callApplicationService
    ) {}
    public function index(): JsonResponse
    {
        $providers = CallProvider::all();

        return response()->json([
            'data' => $providers->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'provider' => $p->provider,
                'phone_number' => $p->phone_number,
                'is_active' => $p->is_active,
                'is_verified' => $p->is_verified,
                'recording_enabled' => $p->recording_enabled,
                'transcription_enabled' => $p->transcription_enabled,
                'last_synced_at' => $p->last_synced_at,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'provider' => 'required|in:twilio,vonage,ringcentral,aircall',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'auth_token' => 'nullable|string',
            'account_sid' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'webhook_url' => 'nullable|url',
            'recording_enabled' => 'boolean',
            'transcription_enabled' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $provider = CallProvider::create([
            ...$validator->validated(),
            'is_active' => false,
            'is_verified' => false,
        ]);

        return response()->json([
            'message' => 'Provider created successfully',
            'data' => [
                'id' => $provider->id,
                'name' => $provider->name,
                'provider' => $provider->provider,
            ],
        ], 201);
    }

    public function show(CallProvider $callProvider): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $callProvider->id,
                'name' => $callProvider->name,
                'provider' => $callProvider->provider,
                'phone_number' => $callProvider->phone_number,
                'webhook_url' => $callProvider->webhook_url,
                'is_active' => $callProvider->is_active,
                'is_verified' => $callProvider->is_verified,
                'recording_enabled' => $callProvider->recording_enabled,
                'transcription_enabled' => $callProvider->transcription_enabled,
                'settings' => $callProvider->settings,
                'last_synced_at' => $callProvider->last_synced_at,
                'created_at' => $callProvider->created_at,
            ],
        ]);
    }

    public function update(Request $request, CallProvider $callProvider): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'auth_token' => 'nullable|string',
            'account_sid' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'webhook_url' => 'nullable|url',
            'recording_enabled' => 'boolean',
            'transcription_enabled' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // If credentials changed, mark as unverified
        if (isset($data['api_key']) || isset($data['auth_token']) || isset($data['account_sid'])) {
            $data['is_verified'] = false;
        }

        $callProvider->update($data);

        return response()->json([
            'message' => 'Provider updated successfully',
            'data' => [
                'id' => $callProvider->id,
                'name' => $callProvider->name,
                'is_verified' => $callProvider->is_verified,
            ],
        ]);
    }

    public function destroy(CallProvider $callProvider): JsonResponse
    {
        // Check for active calls
        if ($callProvider->calls()->whereIn('status', ['ringing', 'in_progress'])->exists()) {
            return response()->json([
                'error' => 'Cannot delete provider with active calls',
            ], 422);
        }

        $callProvider->delete();

        return response()->json([
            'message' => 'Provider deleted successfully',
        ]);
    }

    public function verify(CallProvider $callProvider): JsonResponse
    {
        try {
            if ($callProvider->isTwilio()) {
                $service = new TwilioCallService($callProvider);
                $balance = $service->getAccountBalance();

                if ($balance) {
                    $callProvider->update([
                        'is_verified' => true,
                        'is_active' => true,
                        'last_synced_at' => now(),
                    ]);

                    return response()->json([
                        'message' => 'Provider verified successfully',
                        'data' => [
                            'balance' => $balance['balance'],
                            'currency' => $balance['currency'],
                            'account_status' => $balance['account_status'],
                        ],
                    ]);
                }
            }

            return response()->json([
                'error' => 'Verification failed - check credentials',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Verification failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function toggleActive(CallProvider $callProvider): JsonResponse
    {
        if (!$callProvider->is_verified && !$callProvider->is_active) {
            return response()->json([
                'error' => 'Provider must be verified before activation',
            ], 422);
        }

        $callProvider->update([
            'is_active' => !$callProvider->is_active,
        ]);

        return response()->json([
            'message' => $callProvider->is_active ? 'Provider activated' : 'Provider deactivated',
            'data' => ['is_active' => $callProvider->is_active],
        ]);
    }

    public function listPhoneNumbers(CallProvider $callProvider): JsonResponse
    {
        if (!$callProvider->is_verified) {
            return response()->json([
                'error' => 'Provider must be verified first',
            ], 422);
        }

        if ($callProvider->isTwilio()) {
            $service = new TwilioCallService($callProvider);
            $numbers = $service->listPhoneNumbers();

            return response()->json([
                'data' => $numbers,
            ]);
        }

        return response()->json([
            'error' => 'Provider does not support listing phone numbers',
        ], 422);
    }

    public function syncPhoneNumber(Request $request, CallProvider $callProvider): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $callProvider->update([
            'phone_number' => $request->phone_number,
            'last_synced_at' => now(),
        ]);

        return response()->json([
            'message' => 'Phone number synced successfully',
            'data' => ['phone_number' => $callProvider->phone_number],
        ]);
    }
}
