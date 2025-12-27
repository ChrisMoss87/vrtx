<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Authorization\CachedAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if the authenticated user has ANY of the required permissions.
 *
 * Usage in routes:
 *   Route::get('/admin', [AdminController::class, 'index'])
 *       ->middleware('permission.any:settings.view,users.view,roles.view');
 */
class CheckAnyPermission
{
    public function __construct(
        private readonly CachedAuthorizationService $authService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions  Permission names to check (user must have AT LEAST ONE)
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Use cached authorization service for efficient permission checks
        $userId = $user->id;

        // Admin bypasses all permission checks (cached)
        if ($this->authService->isAdmin($userId)) {
            return $next($request);
        }

        // Check if user has ANY of the required permissions (cached)
        if (!$this->authService->hasAnyPermission($userId, $permissions)) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
                'required_permissions' => $permissions,
            ], 403);
        }

        return $next($request);
    }
}
