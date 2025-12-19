<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ApiKeyController extends Controller
{
    /**
     * List all API keys for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApiKey::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc');

        if ($request->boolean('include_inactive') === false) {
            $query->where('is_active', true);
        }

        $apiKeys = $query->get()->map(fn ($key) => $this->formatApiKey($key));

        return response()->json([
            'data' => $apiKeys,
            'available_scopes' => ApiKey::getAvailableScopes(),
        ]);
    }

    /**
     * Create a new API key.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(array_keys(ApiKey::getAvailableScopes()))],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['ip'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'rate_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $keyData = ApiKey::generateKey();

        $apiKey = ApiKey::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'key' => $keyData['key'],
            'prefix' => $keyData['prefix'],
            'scopes' => $validated['scopes'],
            'allowed_ips' => $validated['allowed_ips'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'rate_limit' => $validated['rate_limit'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'API key created successfully',
            'api_key' => $this->formatApiKey($apiKey),
            'secret' => $keyData['plain_key'], // Only shown once!
            'warning' => 'Store this key securely. It will not be shown again.',
        ], 201);
    }

    /**
     * Get a specific API key.
     */
    public function show(int $id): JsonResponse
    {
        $apiKey = ApiKey::where('user_id', Auth::id())
            ->findOrFail($id);

        // Get recent usage stats
        $recentLogs = $apiKey->requestLogs()
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, AVG(response_time_ms) as avg_response_time')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'data' => $this->formatApiKey($apiKey),
            'usage_stats' => [
                'daily' => $recentLogs,
                'total_requests' => $apiKey->request_count,
            ],
        ]);
    }

    /**
     * Update an API key.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $apiKey = ApiKey::where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scopes' => ['sometimes', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(array_keys(ApiKey::getAvailableScopes()))],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['ip'],
            'is_active' => ['sometimes', 'boolean'],
            'rate_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $apiKey->update($validated);

        return response()->json([
            'message' => 'API key updated successfully',
            'api_key' => $this->formatApiKey($apiKey),
        ]);
    }

    /**
     * Revoke (deactivate) an API key.
     */
    public function revoke(int $id): JsonResponse
    {
        $apiKey = ApiKey::where('user_id', Auth::id())
            ->findOrFail($id);

        $apiKey->revoke();

        return response()->json([
            'message' => 'API key revoked successfully',
        ]);
    }

    /**
     * Delete an API key.
     */
    public function destroy(int $id): JsonResponse
    {
        $apiKey = ApiKey::where('user_id', Auth::id())
            ->findOrFail($id);

        $apiKey->delete();

        return response()->json([
            'message' => 'API key deleted successfully',
        ]);
    }

    /**
     * Regenerate an API key (creates new secret, keeps settings).
     */
    public function regenerate(int $id): JsonResponse
    {
        $apiKey = ApiKey::where('user_id', Auth::id())
            ->findOrFail($id);

        $keyData = ApiKey::generateKey();

        $apiKey->update([
            'key' => $keyData['key'],
            'prefix' => $keyData['prefix'],
            'request_count' => 0,
            'last_used_at' => null,
            'last_used_ip' => null,
        ]);

        return response()->json([
            'message' => 'API key regenerated successfully',
            'api_key' => $this->formatApiKey($apiKey),
            'secret' => $keyData['plain_key'],
            'warning' => 'Store this key securely. It will not be shown again.',
        ]);
    }

    /**
     * Get API key usage logs.
     */
    public function logs(Request $request, int $id): JsonResponse
    {
        $apiKey = ApiKey::where('user_id', Auth::id())
            ->findOrFail($id);

        $query = $apiKey->requestLogs()
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            if ($request->input('status') === 'error') {
                $query->where('status_code', '>=', 400);
            } elseif ($request->input('status') === 'success') {
                $query->where('status_code', '<', 400);
            }
        }

        $logs = $query->paginate($request->integer('per_page', 50));

        return response()->json($logs);
    }

    /**
     * Format API key for response.
     */
    protected function formatApiKey(ApiKey $apiKey): array
    {
        return [
            'id' => $apiKey->id,
            'name' => $apiKey->name,
            'prefix' => $apiKey->prefix,
            'description' => $apiKey->description,
            'scopes' => $apiKey->scopes,
            'allowed_ips' => $apiKey->allowed_ips,
            'is_active' => $apiKey->is_active,
            'rate_limit' => $apiKey->rate_limit,
            'expires_at' => $apiKey->expires_at?->toIso8601String(),
            'last_used_at' => $apiKey->last_used_at?->toIso8601String(),
            'last_used_ip' => $apiKey->last_used_ip,
            'request_count' => $apiKey->request_count,
            'created_at' => $apiKey->created_at->toIso8601String(),
            'updated_at' => $apiKey->updated_at->toIso8601String(),
        ];
    }
}
