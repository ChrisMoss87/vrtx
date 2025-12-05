<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
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

        $apiKey = ApiKey::verify($apiKeyString);

        if (!$apiKey) {
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is invalid, expired, or revoked',
            ], 401);
        }

        // Check IP whitelist
        if (!$apiKey->isIpAllowed($request->ip())) {
            $this->logRequest($apiKey, $request, 403, $startTime);

            return response()->json([
                'error' => 'IP not allowed',
                'message' => 'Your IP address is not in the allowed list for this API key',
            ], 403);
        }

        // Check required scopes
        if (!empty($scopes) && !$apiKey->hasAllScopes($scopes)) {
            $this->logRequest($apiKey, $request, 403, $startTime);

            return response()->json([
                'error' => 'Insufficient permissions',
                'message' => 'This API key does not have the required scopes: ' . implode(', ', $scopes),
            ], 403);
        }

        // Check rate limit
        if ($apiKey->isRateLimited()) {
            $this->logRequest($apiKey, $request, 429, $startTime);

            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => 'You have exceeded the rate limit for this API key',
                'retry_after' => 60,
            ], 429);
        }

        // Record usage
        $apiKey->recordUsage($request->ip());

        // Attach API key to request for use in controllers
        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('api_user_id', $apiKey->user_id);

        $response = $next($request);

        // Log the request
        $this->logRequest($apiKey, $request, $response->getStatusCode(), $startTime);

        return $response;
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
    protected function logRequest(ApiKey $apiKey, Request $request, int $statusCode, float $startTime): void
    {
        $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        ApiRequestLog::create([
            'api_key_id' => $apiKey->id,
            'method' => $request->method(),
            'path' => $request->path(),
            'query_params' => $request->query() ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status_code' => $statusCode,
            'response_time_ms' => $responseTimeMs,
        ]);
    }
}
