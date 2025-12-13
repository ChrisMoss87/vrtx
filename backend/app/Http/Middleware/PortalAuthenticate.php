<?php

namespace App\Http\Middleware;

use App\Models\PortalAccessToken;
use App\Models\PortalUser;
use Closure;
use Illuminate\Http\Request;
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
        $accessToken = PortalAccessToken::where('token', $hashedToken)->first();

        if (!$accessToken) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        if ($accessToken->isExpired()) {
            return response()->json(['message' => 'Token expired'], 401);
        }

        $user = $accessToken->portalUser;

        if (!$user || !$user->is_active) {
            return response()->json(['message' => 'User inactive'], 403);
        }

        // Update last used timestamp
        $accessToken->touch('last_used_at');

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
