<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Services\PluginLicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PluginController extends Controller
{
    public function __construct(
        private PluginLicenseService $licenseService
    ) {}

    /**
     * List all available plugins
     */
    public function index(): JsonResponse
    {
        $plugins = $this->licenseService->getAvailablePlugins();

        // Group by category
        $grouped = $plugins->groupBy('category')->map(function ($items, $category) {
            return [
                'category' => $category,
                'plugins' => $items->values(),
            ];
        })->values();

        return response()->json([
            'plugins' => $plugins,
            'by_category' => $grouped,
        ]);
    }

    /**
     * Get plugin details
     */
    public function show(string $slug): JsonResponse
    {
        $plugin = DB::table('plugins')->where('slug', $slug)->first();

        if (!$plugin) {
            return response()->json(['error' => 'Plugin not found'], 404);
        }

        $plugin->is_licensed = $this->licenseService->hasPlugin($slug);

        return response()->json($plugin);
    }

    /**
     * Get active licenses
     */
    public function licenses(): JsonResponse
    {
        $licenses = PluginLicense::with([])
            ->where('status', PluginLicense::STATUS_ACTIVE)
            ->get()
            ->map(function ($license) {
                $license->plugin = DB::table('plugins')->where('slug', $license->plugin_slug)->first();
                return $license;
            });

        return response()->json([
            'licenses' => $licenses,
        ]);
    }

    /**
     * Activate a plugin (stub for Stripe integration)
     */
    public function activate(Request $request, string $slug): JsonResponse
    {
        $plugin = DB::table('plugins')->where('slug', $slug)->first();

        if (!$plugin) {
            return response()->json(['error' => 'Plugin not found'], 404);
        }

        if ($this->licenseService->hasPlugin($slug)) {
            return response()->json(['error' => 'Plugin already licensed'], 400);
        }

        // In production, this would create a Stripe checkout session
        // For now, we'll create a license directly (for development/testing)
        $license = DB::table('plugin_licenses')->insertGetId([
            'plugin_slug' => $slug,
            'status' => PluginLicense::STATUS_ACTIVE,
            'pricing_model' => $plugin->pricing_model,
            'quantity' => $request->input('quantity', 1),
            'price_monthly' => $plugin->price_monthly,
            'activated_at' => now(),
        ]);

        $this->licenseService->clearCache();

        return response()->json([
            'message' => 'Plugin activated successfully',
            'license' => $license,
        ]);
    }

    /**
     * Deactivate a plugin
     */
    public function deactivate(string $slug): JsonResponse
    {
        $license = DB::table('plugin_licenses')->where('plugin_slug', $slug)
            ->where('status', PluginLicense::STATUS_ACTIVE)
            ->first();

        if (!$license) {
            return response()->json(['error' => 'No active license found'], 404);
        }

        // Check if it's a bundled plugin
        if ($license->bundle_slug) {
            return response()->json([
                'error' => 'Cannot deactivate a bundled plugin individually',
                'bundle' => $license->bundle_slug,
            ], 400);
        }

        $license->update([
            'status' => PluginLicense::STATUS_CANCELLED,
        ]);

        $this->licenseService->clearCache();

        return response()->json([
            'message' => 'Plugin deactivated successfully',
        ]);
    }
}
