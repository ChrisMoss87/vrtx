<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Application\Services\Plugin\PluginApplicationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PluginController extends Controller
{
    public function __construct(
        private PluginApplicationService $pluginService,
    ) {}

    /**
     * List all available plugins.
     */
    public function index(): JsonResponse
    {
        $plugins = $this->pluginService->getAvailablePlugins();
        $grouped = $this->pluginService->getAvailablePluginsGroupedByCategory();

        return response()->json([
            'plugins' => $plugins,
            'by_category' => $grouped,
        ]);
    }

    /**
     * Get plugin details.
     */
    public function show(string $slug): JsonResponse
    {
        $plugin = $this->pluginService->getPluginBySlug($slug);

        if (!$plugin) {
            return response()->json(['error' => 'Plugin not found'], 404);
        }

        $plugin['is_licensed'] = $this->pluginService->isPluginLicensed($slug);

        return response()->json($plugin);
    }

    /**
     * Get active licenses.
     */
    public function licenses(): JsonResponse
    {
        $licenses = $this->pluginService->getActiveLicensesWithPluginDetails();

        return response()->json([
            'licenses' => $licenses,
        ]);
    }

    /**
     * Activate a plugin license.
     */
    public function activate(Request $request, string $slug): JsonResponse
    {
        $result = $this->pluginService->activatePlugin($slug, [
            'quantity' => $request->input('quantity', 1),
            'external_subscription_item_id' => $request->input('external_subscription_item_id'),
        ]);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['errors'][0] ?? 'Failed to activate plugin',
                'errors' => $result['errors'] ?? [],
            ], 400);
        }

        return response()->json([
            'message' => 'Plugin activated successfully',
            'license' => $result['license'],
            'plugin' => $result['plugin'] ?? null,
            'features_unlocked' => $result['features_unlocked'] ?? [],
            'settings_path' => $result['settings_path'] ?? null,
            'next_steps' => $result['next_steps'] ?? [],
        ]);
    }

    /**
     * Deactivate a plugin license.
     */
    public function deactivate(string $slug): JsonResponse
    {
        $result = $this->pluginService->deactivatePlugin($slug);

        if (!$result['success']) {
            $errorMessage = $result['errors'][0] ?? 'Failed to deactivate plugin';
            $statusCode = str_contains($errorMessage, 'bundled') ? 400 : 404;

            return response()->json([
                'error' => $errorMessage,
                'errors' => $result['errors'] ?? [],
            ], $statusCode);
        }

        return response()->json([
            'message' => 'Plugin deactivated successfully',
        ]);
    }

    /**
     * Get license state.
     */
    public function licenseState(): JsonResponse
    {
        return response()->json($this->pluginService->getLicenseState());
    }

    /**
     * Get plugin bundles.
     */
    public function bundles(): JsonResponse
    {
        $bundles = $this->pluginService->getActiveBundles();

        return response()->json([
            'bundles' => $bundles,
        ]);
    }

    /**
     * Get plugin usage stats.
     */
    public function usage(): JsonResponse
    {
        $usage = $this->pluginService->getUsageStats();

        return response()->json([
            'usage' => $usage,
        ]);
    }

    /**
     * Get plugin statistics.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'plugins' => $this->pluginService->getPluginStats(),
            'licenses' => $this->pluginService->getLicenseStats(),
        ]);
    }

    /**
     * Get plugin recommendations.
     */
    public function recommendations(): JsonResponse
    {
        $recommendations = $this->pluginService->getPluginRecommendations();

        return response()->json([
            'recommendations' => $recommendations,
        ]);
    }

    /**
     * Get expiring licenses.
     */
    public function expiring(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);
        $expiring = $this->pluginService->getExpiringLicenses((int) $days);

        return response()->json([
            'expiring_licenses' => $expiring,
        ]);
    }

    /**
     * Check if a specific plugin is licensed.
     */
    public function checkLicense(string $slug): JsonResponse
    {
        $isLicensed = $this->pluginService->isPluginLicensed($slug);
        $license = $this->pluginService->getLicenseForPlugin($slug);

        return response()->json([
            'plugin_slug' => $slug,
            'is_licensed' => $isLicensed,
            'license' => $license,
        ]);
    }
}
