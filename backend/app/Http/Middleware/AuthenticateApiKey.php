<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Services\ApiKey\ApiKeyApplicationService;
use Closure;
use DomainException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to authenticate API requests using API keys.
 *
 * Uses the ApiKeyApplicationService for efficient cached validation
 * with Redis-based rate limiting.
 */
class AuthenticateApiKey
{
    public function __construct(
        private readonly ApiKeyApplicationService $apiKeyService,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string ...$scopes): Response
    {
        $ip = $request->ip() ?? '';

        // Extract API key from request headers only (security: query params could be logged)
        // Note: Query parameter support removed for security - keys in URLs can be logged
        $plainKey = $this->apiKeyService->extractKeyFromRequest(
            $request->header('Authorization'),
            $request->header('X-API-Key'),
            null, // Intentionally disabled query parameter support for security
        );

        if (!$plainKey) {
            return response()->json([
                'error' => 'API key required',
                'message' => 'Provide API key via Authorization header (Bearer token) or X-API-Key header',
            ], 401);
        }

        try {
            // Validate with rate limit check using cached service
            $requiredScope = !empty($scopes) ? $scopes[0] : null;
            $apiKey = $this->apiKeyService->validateApiKeyWithRateLimit($plainKey, $ip, $requiredScope);

            // Check additional scopes if multiple required
            if (count($scopes) > 1) {
                foreach (array_slice($scopes, 1) as $scope) {
                    if (!$apiKey->hasScope($scope)) {
                        throw new DomainException("Missing required scope: {$scope}");
                    }
                }
            }

            // Attach API key info to request for use in controllers
            $request->attributes->set('api_key', $apiKey);
            $request->attributes->set('api_user_id', $apiKey->getUserId());
            $request->attributes->set('api_key_id', $apiKey->getIdValue());

            $response = $next($request);

            // Record the request for logging and analytics
            $this->apiKeyService->recordRequest(
                $apiKey,
                $request->path(),
                $request->method(),
                $ip,
                $response->getStatusCode(),
            );

            return $response;
        } catch (DomainException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'Invalid API key')) {
                return response()->json([
                    'error' => 'Invalid API key',
                    'message' => 'The provided API key is invalid, expired, or revoked',
                ], 401);
            }

            if (str_contains($message, 'IP')) {
                return response()->json([
                    'error' => 'IP not allowed',
                    'message' => 'Your IP address is not in the allowed list for this API key',
                ], 403);
            }

            if (str_contains($message, 'Rate limit')) {
                return response()->json([
                    'error' => 'Rate limit exceeded',
                    'message' => 'You have exceeded the rate limit for this API key',
                    'retry_after' => 60,
                ], 429);
            }

            if (str_contains($message, 'scope') || str_contains($message, 'Scope')) {
                return response()->json([
                    'error' => 'Insufficient permissions',
                    'message' => $message,
                ], 403);
            }

            if (str_contains($message, 'expired') || str_contains($message, 'inactive')) {
                return response()->json([
                    'error' => 'API key invalid',
                    'message' => $message,
                ], 401);
            }

            return response()->json([
                'error' => 'Authentication failed',
                'message' => $message,
            ], 401);
        }
    }
}
