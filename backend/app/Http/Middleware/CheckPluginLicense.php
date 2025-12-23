<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Plugin\Repositories\PluginRepositoryInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPluginLicense
{
    public function __construct(
        private readonly PluginRepositoryInterface $pluginRepository,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $pluginSlug): Response
    {
        // In development/testing, always allow
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        // Check if plugin is installed and active for current tenant
        if (!$this->pluginRepository->isPluginInstalled($pluginSlug)) {
            return response()->json([
                'message' => 'This feature requires an active plugin license.',
                'plugin' => $pluginSlug,
                'error' => 'plugin_not_licensed',
                'upgrade_url' => '/settings/billing/plugins',
            ], Response::HTTP_FORBIDDEN);
        }

        // Check usage limits if applicable
        $usageMetrics = $this->pluginRepository->getPluginUsageMetrics($pluginSlug);

        if (!empty($usageMetrics)) {
            $usageCheck = $this->checkUsageLimits($pluginSlug, $usageMetrics);

            if (!$usageCheck['allowed']) {
                return response()->json([
                    'message' => $usageCheck['message'],
                    'plugin' => $pluginSlug,
                    'error' => 'usage_limit_exceeded',
                    'limit' => $usageCheck['limit'] ?? null,
                    'current' => $usageCheck['current'] ?? null,
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }
        }

        return $next($request);
    }

    /**
     * Check plugin usage limits.
     */
    private function checkUsageLimits(string $pluginSlug, array $usageMetrics): array
    {
        foreach ($usageMetrics as $metric) {
            $metricName = $metric['metric'] ?? $metric['name'] ?? null;
            $limit = $metric['limit_quantity'] ?? $metric['limit'] ?? null;

            if (!$metricName || !$limit) {
                continue;
            }

            $currentUsage = $metric['current_usage'] ?? $this->pluginRepository->getCurrentUsage(
                $pluginSlug,
                $metricName,
                $metric['reset_period'] ?? 'monthly'
            );

            if ($currentUsage >= $limit) {
                return [
                    'allowed' => false,
                    'message' => "Usage limit exceeded for {$metricName}.",
                    'limit' => $limit,
                    'current' => $currentUsage,
                ];
            }
        }

        return ['allowed' => true];
    }
}
