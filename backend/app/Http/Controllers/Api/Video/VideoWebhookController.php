<?php

namespace App\Http\Controllers\Api\Video;

use App\Application\Services\Video\VideoApplicationService;
use App\Http\Controllers\Controller;
use App\Models\VideoProvider;
use App\Services\Video\ZoomService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class VideoWebhookController extends Controller
{
    public function __construct(
        protected VideoApplicationService $videoApplicationService
    ) {}

    public function zoom(Request $request): JsonResponse
    {
        // Handle Zoom URL validation challenge
        if ($request->has('payload') && isset($request->payload['plainToken'])) {
            $plainToken = $request->payload['plainToken'];
            $provider = VideoProvider::where('provider', 'zoom')
                ->where('is_active', true)
                ->first();

            if ($provider && $provider->webhook_secret) {
                $hashForValidate = hash_hmac('sha256', $plainToken, $provider->webhook_secret);

                return response()->json([
                    'plainToken' => $plainToken,
                    'encryptedToken' => $hashForValidate,
                ]);
            }
        }

        // Verify webhook signature
        $signature = $request->header('x-zm-signature');
        $timestamp = $request->header('x-zm-request-timestamp');

        $provider = VideoProvider::where('provider', 'zoom')
            ->where('is_active', true)
            ->first();

        if (!$provider) {
            Log::warning('Zoom webhook received but no active provider found');
            return response()->json(['message' => 'No active provider'], 200);
        }

        if ($provider->webhook_secret && $signature) {
            $message = "v0:{$timestamp}:" . $request->getContent();
            $expectedSignature = 'v0=' . hash_hmac('sha256', $message, $provider->webhook_secret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::warning('Zoom webhook signature verification failed');
                return response()->json(['message' => 'Invalid signature'], 401);
            }
        }

        $payload = $request->all();
        $event = $payload['event'] ?? 'unknown';

        Log::info('Zoom webhook received', [
            'event' => $event,
            'meeting_id' => $payload['payload']['object']['id'] ?? null,
        ]);

        try {
            $service = new ZoomService($provider);
            $result = $service->handleWebhook($payload);

            return response()->json([
                'message' => 'Webhook processed',
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Zoom webhook processing failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    public function oauthCallback(Request $request): JsonResponse
    {
        $code = $request->input('code');
        $state = $request->input('state');

        if (!$code || !$state) {
            return response()->json([
                'message' => 'Missing code or state parameter',
            ], 400);
        }

        try {
            $providerId = decrypt($state);
            $provider = VideoProvider::findOrFail($providerId);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid state parameter',
            ], 400);
        }

        $redirectUri = config('app.url') . '/api/v1/video/oauth/callback';

        try {
            $response = match ($provider->provider) {
                'zoom' => $this->exchangeZoomCode($provider, $code, $redirectUri),
                'google_meet' => $this->exchangeGoogleCode($provider, $code, $redirectUri),
                default => throw new \Exception('OAuth not supported for this provider'),
            };

            return response()->json([
                'message' => 'OAuth completed successfully',
                'provider' => $provider->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('OAuth callback failed', [
                'provider' => $provider->provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'OAuth failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    protected function exchangeZoomCode(VideoProvider $provider, string $code, string $redirectUri): void
    {
        $response = \Illuminate\Support\Facades\Http::asForm()
            ->withBasicAuth($provider->client_id, $provider->client_secret)
            ->post('https://zoom.us/oauth/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if ($response->failed()) {
            throw new \Exception('Failed to exchange code: ' . $response->body());
        }

        $data = $response->json();

        $provider->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'token_expires_at' => now()->addSeconds($data['expires_in']),
            'is_verified' => true,
        ]);
    }

    protected function exchangeGoogleCode(VideoProvider $provider, string $code, string $redirectUri): void
    {
        $response = \Illuminate\Support\Facades\Http::asForm()
            ->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $provider->client_id,
                'client_secret' => $provider->client_secret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

        if ($response->failed()) {
            throw new \Exception('Failed to exchange code: ' . $response->body());
        }

        $data = $response->json();

        $provider->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'token_expires_at' => now()->addSeconds($data['expires_in']),
            'is_verified' => true,
        ]);
    }
}
