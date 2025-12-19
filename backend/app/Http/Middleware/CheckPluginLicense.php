<?php

namespace App\Http\Middleware;

use App\Services\PluginLicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPluginLicense
{
    public function __construct(
        private PluginLicenseService $licenseService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $pluginSlug): Response
    {
        if (!$this->licenseService->hasPlugin($pluginSlug)) {
            return response()->json([
                'error' => 'Plugin not licensed',
                'message' => "This feature requires the '{$pluginSlug}' plugin.",
                'plugin' => $pluginSlug,
                'upgrade_url' => '/settings/billing/plugins',
            ], 403);
        }

        return $next($request);
    }
}
