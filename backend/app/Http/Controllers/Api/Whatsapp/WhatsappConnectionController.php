<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Application\Services\WhatsApp\WhatsAppApplicationService;
use App\Http\Controllers\Controller;
use App\Services\Whatsapp\WhatsappApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsappConnectionController extends Controller
{
    public function __construct(
        protected WhatsAppApplicationService $whatsAppApplicationService
    ) {}
    public function index(): JsonResponse
    {
        $connections = WhatsappConnection::withCount(['conversations', 'templates'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $connections]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number_id' => 'required|string|max:255',
            'waba_id' => 'nullable|string|max:255',
            'access_token' => 'required|string',
            'display_phone_number' => 'nullable|string|max:50',
            'settings' => 'nullable|array',
        ]);

        $connection = DB::table('whatsapp_connections')->insertGetId($validated);

        // Try to fetch phone number info to verify connection
        try {
            $api = WhatsappApiService::for($connection);
            $info = $api->getPhoneNumberInfo();

            if ($info) {
                $connection->update([
                    'display_phone_number' => $info['display_phone_number'] ?? $connection->display_phone_number,
                    'verified_name' => $info['verified_name'] ?? null,
                    'quality_rating' => $info['quality_rating'] ?? null,
                    'is_verified' => true,
                    'last_synced_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // Connection created but not verified
        }

        return response()->json([
            'data' => $connection->fresh(),
            'message' => $connection->is_verified
                ? 'WhatsApp connection created and verified'
                : 'WhatsApp connection created but could not verify. Please check credentials.',
        ], 201);
    }

    public function show(WhatsappConnection $connection): JsonResponse
    {
        $connection->loadCount(['conversations', 'templates', 'messages']);

        return response()->json(['data' => $connection]);
    }

    public function update(Request $request, WhatsappConnection $connection): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone_number_id' => 'sometimes|string|max:255',
            'waba_id' => 'nullable|string|max:255',
            'access_token' => 'sometimes|string',
            'display_phone_number' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
            'settings' => 'nullable|array',
        ]);

        $connection->update($validated);

        return response()->json(['data' => $connection->fresh()]);
    }

    public function destroy(WhatsappConnection $connection): JsonResponse
    {
        $connection->delete();

        return response()->json(null, 204);
    }

    public function verify(WhatsappConnection $connection): JsonResponse
    {
        try {
            $api = WhatsappApiService::for($connection);
            $info = $api->getPhoneNumberInfo();

            if ($info) {
                $connection->update([
                    'display_phone_number' => $info['display_phone_number'] ?? $connection->display_phone_number,
                    'verified_name' => $info['verified_name'] ?? null,
                    'quality_rating' => $info['quality_rating'] ?? null,
                    'messaging_limit' => $info['messaging_limit_tier'] ?? null,
                    'is_verified' => true,
                    'last_synced_at' => now(),
                ]);

                return response()->json([
                    'data' => $connection->fresh(),
                    'message' => 'Connection verified successfully',
                ]);
            }

            return response()->json([
                'message' => 'Could not verify connection. Please check credentials.',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Verification failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function getWebhookConfig(WhatsappConnection $connection): JsonResponse
    {
        $webhookUrl = url('/api/v1/whatsapp/webhook/' . $connection->id);

        return response()->json([
            'data' => [
                'webhook_url' => $webhookUrl,
                'verify_token' => $connection->webhook_verify_token,
                'instructions' => [
                    '1. Go to your Meta App Dashboard',
                    '2. Navigate to WhatsApp > Configuration',
                    '3. Set the Webhook URL to: ' . $webhookUrl,
                    '4. Set the Verify Token to: ' . $connection->webhook_verify_token,
                    '5. Subscribe to: messages, message_status_updates',
                ],
            ],
        ]);
    }
}
