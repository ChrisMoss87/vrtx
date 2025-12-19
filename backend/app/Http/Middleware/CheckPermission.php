<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if the authenticated user has the required permission.
 *
 * Usage in routes:
 *   Route::get('/settings', [SettingsController::class, 'index'])
 *       ->middleware('permission:settings.view');
 *
 *   Route::post('/users', [UserController::class, 'store'])
 *       ->middleware('permission:users.create');
 *
 * Multiple permissions (user must have ALL):
 *   ->middleware('permission:users.view,users.edit');
 *
 * Any permission (user must have AT LEAST ONE):
 *   ->middleware('permission.any:users.create,users.edit');
 */
class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions  Permission names to check (user must have ALL)
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Admin bypasses all permission checks
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // Check if user has ALL required permissions
        foreach ($permissions as $permission) {
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'message' => 'You do not have permission to perform this action.',
                    'required_permission' => $permission,
                ], 403);
            }
        }

        return $next($request);
    }
}
