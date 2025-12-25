<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PortalAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $hashedToken = hash('sha256', $token);
        $accessToken = DB::table('portal_access_tokens')
            ->where('token', $hashedToken)
            ->first();

        if (!$accessToken) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        // Check if token is expired
        if ($accessToken->expires_at && now()->isAfter($accessToken->expires_at)) {
            return response()->json(['message' => 'Token expired'], 401);
        }

        $user = DB::table('portal_users')
            ->where('id', $accessToken->portal_user_id)
            ->first();

        if (!$user || !$user->is_active) {
            return response()->json(['message' => 'User inactive'], 403);
        }

        // Update last used timestamp
        DB::table('portal_access_tokens')
            ->where('id', $accessToken->id)
            ->update(['last_used_at' => now()]);

        // Attach user and token to request for use in controllers
        $request->attributes->set('portal_user', $user);
        $request->attributes->set('portal_token', $accessToken);

        return $next($request);
    }

    private function getTokenFromRequest(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return $request->query('token');
    }
}
