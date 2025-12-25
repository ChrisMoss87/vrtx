<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string ...$scopes): Response
    {
        $startTime = microtime(true);

        $apiKeyString = $this->extractApiKey($request);

        if (!$apiKeyString) {
            return response()->json([
                'error' => 'API key required',
                'message' => 'Provide API key via Authorization header (Bearer token) or X-API-Key header',
            ], 401);
        }

        $apiKey = $this->verifyApiKey($apiKeyString);

        if (!$apiKey) {
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is invalid, expired, or revoked',
            ], 401);
        }

        // Check IP whitelist
        if (!$this->isIpAllowed($apiKey, $request->ip())) {
            $this->logRequest($apiKey, $request, 403, $startTime);

            return response()->json([
                'error' => 'IP not allowed',
                'message' => 'Your IP address is not in the allowed list for this API key',
            ], 403);
        }

        // Check required scopes
        if (!empty($scopes) && !$this->hasAllScopes($apiKey, $scopes)) {
            $this->logRequest($apiKey, $request, 403, $startTime);

            return response()->json([
                'error' => 'Insufficient permissions',
                'message' => 'This API key does not have the required scopes: ' . implode(', ', $scopes),
            ], 403);
        }

        // Check rate limit
        if ($this->isRateLimited($apiKey)) {
            $this->logRequest($apiKey, $request, 429, $startTime);

            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => 'You have exceeded the rate limit for this API key',
                'retry_after' => 60,
            ], 429);
        }

        // Record usage
        $this->recordUsage($apiKey, $request->ip());

        // Attach API key to request for use in controllers
        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('api_user_id', $apiKey->user_id);

        $response = $next($request);

        // Log the request
        $this->logRequest($apiKey, $request, $response->getStatusCode(), $startTime);

        return $response;
    }

    /**
     * Verify API key and return it if valid.
     */
    protected function verifyApiKey(string $keyString): ?object
    {
        $hashedKey = hash('sha256', $keyString);

        $apiKey = DB::table('api_keys')
            ->where('key', $hashedKey)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $apiKey;
    }

    /**
     * Check if IP is allowed.
     */
    protected function isIpAllowed(object $apiKey, ?string $ip): bool
    {
        if (empty($apiKey->ip_whitelist)) {
            return true;
        }

        $whitelist = is_string($apiKey->ip_whitelist)
            ? json_decode($apiKey->ip_whitelist, true)
            : $apiKey->ip_whitelist;

        if (empty($whitelist)) {
            return true;
        }

        return in_array($ip, $whitelist);
    }

    /**
     * Check if API key has all required scopes.
     */
    protected function hasAllScopes(object $apiKey, array $requiredScopes): bool
    {
        $keyScopes = is_string($apiKey->scopes ?? null)
            ? json_decode($apiKey->scopes, true)
            : ($apiKey->scopes ?? []);

        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $keyScopes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if API key is rate limited.
     */
    protected function isRateLimited(object $apiKey): bool
    {
        $rateLimit = $apiKey->rate_limit ?? 1000;

        $requestCount = DB::table('api_request_logs')
            ->where('api_key_id', $apiKey->id)
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        return $requestCount >= $rateLimit;
    }

    /**
     * Record API key usage.
     */
    protected function recordUsage(object $apiKey, ?string $ip): void
    {
        DB::table('api_keys')
            ->where('id', $apiKey->id)
            ->update([
                'last_used_at' => now(),
                'last_used_ip' => $ip,
                'usage_count' => DB::raw('usage_count + 1'),
            ]);
    }

    /**
     * Extract API key from request.
     */
    protected function extractApiKey(Request $request): ?string
    {
        // Try Authorization header first (Bearer token)
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Try X-API-Key header
        $apiKeyHeader = $request->header('X-API-Key');
        if ($apiKeyHeader) {
            return $apiKeyHeader;
        }

        // Try query parameter (least secure, discouraged)
        $queryKey = $request->query('api_key');
        if ($queryKey) {
            return $queryKey;
        }

        return null;
    }

    /**
     * Log the API request.
     */
    protected function logRequest(object $apiKey, Request $request, int $statusCode, float $startTime): void
    {
        $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        DB::table('api_request_logs')->insert([
            'api_key_id' => $apiKey->id,
            'method' => $request->method(),
            'path' => $request->path(),
            'query_params' => $request->query() ? json_encode($request->query()) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status_code' => $statusCode,
            'response_time_ms' => $responseTimeMs,
            'created_at' => now(),
        ]);
    }
}
