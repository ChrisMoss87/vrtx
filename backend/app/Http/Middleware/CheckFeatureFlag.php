<?php

namespace App\Http\Middleware;

use App\Services\PluginLicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureFlag
{
    public function __construct(
        private PluginLicenseService $licenseService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        if (!$this->licenseService->hasFeature($featureKey)) {
            return response()->json([
                'error' => 'Feature not available',
                'message' => "This feature requires an upgrade to access '{$featureKey}'.",
                'feature' => $featureKey,
                'upgrade_url' => '/settings/billing/plans',
            ], 403);
        }

        return $next($request);
    }
}
