<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Services\PluginLicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function __construct(
        private PluginLicenseService $licenseService
    ) {}

    /**
     * Get current license state
     */
    public function show(): JsonResponse
    {
        return response()->json($this->licenseService->getLicenseState());
    }

    /**
     * Check if a specific plugin is licensed
     */
    public function checkPlugin(string $pluginSlug): JsonResponse
    {
        return response()->json([
            'plugin' => $pluginSlug,
            'licensed' => $this->licenseService->hasPlugin($pluginSlug),
        ]);
    }

    /**
     * Check if a specific feature is enabled
     */
    public function checkFeature(string $featureKey): JsonResponse
    {
        return response()->json([
            'feature' => $featureKey,
            'enabled' => $this->licenseService->hasFeature($featureKey),
        ]);
    }

    /**
     * Get usage stats
     */
    public function usage(): JsonResponse
    {
        return response()->json([
            'usage' => $this->licenseService->getUsageStats(),
        ]);
    }

    /**
     * Get usage for a specific metric
     */
    public function usageMetric(string $metric): JsonResponse
    {
        return response()->json([
            'metric' => $metric,
            'usage' => $this->licenseService->checkUsageLimit($metric),
        ]);
    }
}
