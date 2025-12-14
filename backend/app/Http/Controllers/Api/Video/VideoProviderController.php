<?php

namespace App\Http\Controllers\Api\Video;

use App\Application\Services\Video\VideoApplicationService;
use App\Http\Controllers\Controller;
use App\Models\VideoProvider;
use App\Services\Video\ZoomService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VideoProviderController extends Controller
{
    public function __construct(
        protected VideoApplicationService $videoApplicationService
    ) {}

    public function index(): JsonResponse
    {
        $providers = VideoProvider::orderBy('name')->get();

        return response()->json([
            'data' => $providers,
        ]);
    }

    public function show(VideoProvider $videoProvider): JsonResponse
    {
        return response()->json([
            'data' => $videoProvider,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|string|in:zoom,teams,google_meet,webex',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'webhook_secret' => 'nullable|string',
            'settings' => 'nullable|array',
            'scopes' => 'nullable|array',
        ]);

        $provider = VideoProvider::create($validated);

        return response()->json([
            'data' => $provider,
            'message' => 'Video provider created successfully',
        ], 201);
    }

    public function update(Request $request, VideoProvider $videoProvider): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'webhook_secret' => 'nullable|string',
            'settings' => 'nullable|array',
            'scopes' => 'nullable|array',
        ]);

        $videoProvider->update($validated);

        return response()->json([
            'data' => $videoProvider->fresh(),
            'message' => 'Video provider updated successfully',
        ]);
    }

    public function destroy(VideoProvider $videoProvider): JsonResponse
    {
        // Check for active meetings
        if ($videoProvider->meetings()->where('status', 'scheduled')->exists()) {
            return response()->json([
                'message' => 'Cannot delete provider with scheduled meetings',
            ], 422);
        }

        $videoProvider->delete();

        return response()->json([
            'message' => 'Video provider deleted successfully',
        ]);
    }

    public function verify(VideoProvider $videoProvider): JsonResponse
    {
        try {
            $service = match ($videoProvider->provider) {
                'zoom' => new ZoomService($videoProvider),
                default => throw new \Exception("Provider {$videoProvider->provider} verification not implemented"),
            };

            $verified = $service->verifyConnection();

            $videoProvider->update([
                'is_verified' => $verified,
                'last_synced_at' => now(),
            ]);

            return response()->json([
                'data' => $videoProvider->fresh(),
                'verified' => $verified,
                'message' => $verified
                    ? 'Provider connection verified successfully'
                    : 'Provider connection verification failed',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'verified' => false,
                'message' => 'Verification failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function toggleActive(VideoProvider $videoProvider): JsonResponse
    {
        // Can only activate verified providers
        if (!$videoProvider->is_verified && !$videoProvider->is_active) {
            return response()->json([
                'message' => 'Provider must be verified before activation',
            ], 422);
        }

        $videoProvider->update([
            'is_active' => !$videoProvider->is_active,
        ]);

        return response()->json([
            'data' => $videoProvider->fresh(),
            'message' => $videoProvider->is_active
                ? 'Provider activated successfully'
                : 'Provider deactivated successfully',
        ]);
    }

    public function getOAuthUrl(VideoProvider $videoProvider): JsonResponse
    {
        $redirectUri = config('app.url') . '/api/v1/video/oauth/callback';

        $url = match ($videoProvider->provider) {
            'zoom' => $this->getZoomOAuthUrl($videoProvider, $redirectUri),
            'google_meet' => $this->getGoogleOAuthUrl($videoProvider, $redirectUri),
            default => null,
        };

        if (!$url) {
            return response()->json([
                'message' => 'OAuth not supported for this provider',
            ], 422);
        }

        return response()->json([
            'oauth_url' => $url,
        ]);
    }

    protected function getZoomOAuthUrl(VideoProvider $provider, string $redirectUri): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $provider->client_id,
            'redirect_uri' => $redirectUri,
            'state' => encrypt($provider->id),
        ]);

        return "https://zoom.us/oauth/authorize?{$params}";
    }

    protected function getGoogleOAuthUrl(VideoProvider $provider, string $redirectUri): string
    {
        $params = http_build_query([
            'client_id' => $provider->client_id,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'state' => encrypt($provider->id),
        ]);

        return "https://accounts.google.com/o/oauth2/v2/auth?{$params}";
    }
}
