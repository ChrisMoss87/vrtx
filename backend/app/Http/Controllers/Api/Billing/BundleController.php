<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plugin;
use App\Models\PluginBundle;
use App\Models\PluginLicense;
use App\Services\PluginLicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BundleController extends Controller
{
    public function __construct(
        private PluginLicenseService $licenseService
    ) {}

    /**
     * List all available bundles
     */
    public function index(): JsonResponse
    {
        $bundles = $this->licenseService->getAvailableBundles();

        return response()->json([
            'bundles' => $bundles,
        ]);
    }

    /**
     * Get bundle details
     */
    public function show(string $slug): JsonResponse
    {
        $bundle = PluginBundle::where('slug', $slug)->first();

        if (!$bundle) {
            return response()->json(['error' => 'Bundle not found'], 404);
        }

        // Get the actual plugin models
        $bundle->plugin_details = Plugin::whereIn('slug', $bundle->plugins ?? [])->get();

        // Check which are already licensed
        $licensedPlugins = $this->licenseService->getLicensedPlugins();
        $bundle->licensed_plugins = array_intersect($bundle->plugins ?? [], $licensedPlugins);
        $bundle->is_fully_licensed = count($bundle->licensed_plugins) === count($bundle->plugins);

        return response()->json($bundle);
    }

    /**
     * Activate a bundle (stub for Stripe integration)
     */
    public function activate(Request $request, string $slug): JsonResponse
    {
        $bundle = PluginBundle::where('slug', $slug)->first();

        if (!$bundle) {
            return response()->json(['error' => 'Bundle not found'], 404);
        }

        $pluginSlugs = $bundle->plugins ?? [];
        $activatedPlugins = [];

        // In production, this would create a Stripe checkout session
        // For now, we'll create licenses directly (for development/testing)
        foreach ($pluginSlugs as $pluginSlug) {
            // Skip if already licensed
            if ($this->licenseService->hasPlugin($pluginSlug)) {
                continue;
            }

            $plugin = Plugin::where('slug', $pluginSlug)->first();
            if (!$plugin) {
                continue;
            }

            PluginLicense::create([
                'plugin_slug' => $pluginSlug,
                'bundle_slug' => $slug,
                'status' => PluginLicense::STATUS_ACTIVE,
                'pricing_model' => $plugin->pricing_model,
                'quantity' => $request->input('quantity', 1),
                'price_monthly' => 0, // Bundle price is tracked separately
                'activated_at' => now(),
            ]);

            $activatedPlugins[] = $pluginSlug;
        }

        $this->licenseService->clearCache();

        return response()->json([
            'message' => 'Bundle activated successfully',
            'bundle' => $slug,
            'activated_plugins' => $activatedPlugins,
        ]);
    }

    /**
     * Deactivate a bundle
     */
    public function deactivate(string $slug): JsonResponse
    {
        $licenses = PluginLicense::where('bundle_slug', $slug)
            ->where('status', PluginLicense::STATUS_ACTIVE)
            ->get();

        if ($licenses->isEmpty()) {
            return response()->json(['error' => 'No active bundle license found'], 404);
        }

        foreach ($licenses as $license) {
            $license->update([
                'status' => PluginLicense::STATUS_CANCELLED,
            ]);
        }

        $this->licenseService->clearCache();

        return response()->json([
            'message' => 'Bundle deactivated successfully',
            'deactivated_plugins' => $licenses->pluck('plugin_slug'),
        ]);
    }
}
