<?php

namespace App\Http\Middleware;

use App\Services\PluginLicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlan
{
    public function __construct(
        private PluginLicenseService $licenseService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $requiredPlan): Response
    {
        if (!$this->licenseService->hasPlan($requiredPlan)) {
            $currentPlan = $this->licenseService->getCurrentPlan();

            return response()->json([
                'error' => 'Plan upgrade required',
                'message' => "This feature requires the '{$requiredPlan}' plan or higher. You are currently on the '{$currentPlan}' plan.",
                'current_plan' => $currentPlan,
                'required_plan' => $requiredPlan,
                'upgrade_url' => '/settings/billing/plans',
            ], 403);
        }

        return $next($request);
    }
}
