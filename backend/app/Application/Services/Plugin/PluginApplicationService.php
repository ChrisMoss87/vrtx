<?php

declare(strict_types=1);

namespace App\Application\Services\Plugin;

use App\Domain\Plugin\Entities\Plugin;
use App\Domain\Plugin\Entities\PluginLicense;
use App\Domain\Plugin\Repositories\PluginRepositoryInterface;
use App\Domain\Plugin\Services\PluginLicenseValidationService;
use App\Domain\Plugin\Services\PluginRequirementsService;
use App\Domain\Plugin\Services\PluginUsageService;
use App\Domain\Plugin\ValueObjects\LicenseStatus;
use App\Domain\Plugin\ValueObjects\Money;
use App\Domain\Plugin\ValueObjects\PluginSlug;
use App\Domain\Plugin\ValueObjects\PricingModel;
use App\Domain\Shared\Contracts\AuthContextInterface;
use Illuminate\Support\Facades\Cache;

class PluginApplicationService
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private PluginRepositoryInterface $repository,
        private AuthContextInterface $authContext,
        private PluginLicenseValidationService $licenseValidation,
        private PluginRequirementsService $requirementsService,
        private PluginUsageService $usageService,
    ) {}

    // =========================================================================
    // QUERY USE CASES - PLUGIN CATALOG
    // =========================================================================

    public function listPlugins(array $filters = []): array
    {
        return $this->repository->listPlugins($filters);
    }

    public function getPlugin(int $id): ?array
    {
        return $this->repository->findByIdAsArray($id);
    }

    public function getPluginBySlug(string $slug): ?array
    {
        return $this->repository->findBySlug($slug);
    }

    public function getPluginsByCategory(string $category): array
    {
        return $this->repository->getPluginsByCategory($category);
    }

    public function getAvailablePlugins(): array
    {
        $plugins = $this->repository->getAvailablePlugins();
        $licensedPluginSlugs = $this->getLicensedPluginSlugs();

        // Annotate plugins with license status
        return array_map(function (array $plugin) use ($licensedPluginSlugs) {
            $plugin['is_licensed'] = in_array($plugin['slug'], $licensedPluginSlugs, true);
            return $plugin;
        }, $plugins);
    }

    public function getAvailablePluginsGroupedByCategory(): array
    {
        $plugins = $this->getAvailablePlugins();
        $grouped = [];

        foreach ($plugins as $plugin) {
            $category = $plugin['category'] ?? 'other';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [
                    'category' => $category,
                    'plugins' => [],
                ];
            }
            $grouped[$category]['plugins'][] = $plugin;
        }

        return array_values($grouped);
    }

    // =========================================================================
    // QUERY USE CASES - BUNDLES
    // =========================================================================

    public function listBundles(array $filters = []): array
    {
        return $this->repository->listBundles($filters);
    }

    public function getBundle(int $id): ?array
    {
        return $this->repository->findBundleById($id);
    }

    public function getBundleBySlug(string $slug): ?array
    {
        return $this->repository->findBundleBySlug($slug);
    }

    public function getActiveBundles(): array
    {
        $bundles = $this->repository->getActiveBundles();
        $licensedPluginSlugs = $this->getLicensedPluginSlugs();

        return array_map(function (array $bundle) use ($licensedPluginSlugs) {
            $bundlePlugins = $bundle['plugins'] ?? [];
            $licensedCount = count(array_intersect($bundlePlugins, $licensedPluginSlugs));
            $bundle['licensed_count'] = $licensedCount;
            $bundle['is_fully_licensed'] = $licensedCount === count($bundlePlugins);
            return $bundle;
        }, $bundles);
    }

    // =========================================================================
    // QUERY USE CASES - LICENSES
    // =========================================================================

    public function listLicenses(array $filters = []): array
    {
        return $this->repository->listLicenses($filters);
    }

    public function getActiveLicenses(): array
    {
        return $this->repository->getActiveLicenses();
    }

    public function getActiveLicensesWithPluginDetails(): array
    {
        $licenses = $this->repository->getActiveLicenses();
        $result = [];

        foreach ($licenses as $license) {
            $plugin = $this->repository->findBySlug($license['plugin_slug']);
            if ($plugin) {
                $license['plugin'] = $plugin;
            }
            $result[] = $license;
        }

        return $result;
    }

    public function getLicenseForPlugin(string $pluginSlug): ?array
    {
        return $this->repository->getLicenseForPlugin($pluginSlug);
    }

    public function isPluginLicensed(string $pluginSlug): bool
    {
        $cacheKey = "plugin_license:{$pluginSlug}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($pluginSlug) {
            // Check if included in current plan
            $currentPlan = $this->getCurrentPlan();
            if ($this->licenseValidation->isPluginIncludedInPlan(
                PluginSlug::fromString($pluginSlug),
                $currentPlan
            )) {
                return true;
            }

            // Check for active license
            return $this->repository->isPluginInstalled($pluginSlug);
        });
    }

    public function getLicensedPluginSlugs(): array
    {
        return Cache::remember('licensed_plugins', self::CACHE_TTL, function () {
            $currentPlan = $this->getCurrentPlan();

            // Get plugins included in plan
            $includedPlugins = $this->licenseValidation->getPluginsForPlan($currentPlan);

            // Get additional licensed plugins
            $licenses = $this->repository->getActiveLicenses();
            $licensedPlugins = array_column($licenses, 'plugin_slug');

            return array_unique(array_merge($includedPlugins, $licensedPlugins));
        });
    }

    public function getInstalledPlugins(): array
    {
        return $this->repository->getInstalledPlugins();
    }

    // =========================================================================
    // COMMAND USE CASES - LICENSE MANAGEMENT
    // =========================================================================

    /**
     * Activate a plugin license.
     *
     * @return array{success: bool, license?: array, errors?: string[]}
     */
    public function activatePlugin(string $pluginSlug, array $options = []): array
    {
        $plugin = $this->repository->findBySlug($pluginSlug);
        if (!$plugin) {
            return [
                'success' => false,
                'errors' => ['Plugin not found'],
            ];
        }

        $existingLicense = $this->repository->getLicenseForPlugin($pluginSlug);
        $currentPlan = $this->getCurrentPlan();

        // Check if already included in plan
        if ($this->licenseValidation->isPluginIncludedInPlan(
            PluginSlug::fromString($pluginSlug),
            $currentPlan
        )) {
            return [
                'success' => false,
                'errors' => ['Plugin is already included in your current plan'],
            ];
        }

        // Validate license creation
        // Note: We'd need to reconstitute entities here for proper validation
        // For now, we'll do basic validation with arrays
        if ($existingLicense !== null && $existingLicense['status'] === LicenseStatus::ACTIVE) {
            return [
                'success' => false,
                'errors' => ['Plugin is already licensed'],
            ];
        }

        // Create the license
        $licenseData = [
            'plugin_slug' => $pluginSlug,
            'bundle_slug' => $options['bundle_slug'] ?? null,
            'pricing_model' => $plugin['pricing_model'] ?? PricingModel::PER_USER,
            'quantity' => $options['quantity'] ?? 1,
            'price_monthly' => $plugin['price_monthly'] ?? null,
            'external_subscription_item_id' => $options['external_subscription_item_id'] ?? null,
            'expires_at' => $options['expires_at'] ?? null,
        ];

        $license = $this->repository->activatePlugin($licenseData);

        $this->clearCache();

        // Build detailed response with next steps
        $features = $this->parseFeatures($plugin['features'] ?? null);
        $settingsPath = $this->getPluginSettingsPath($pluginSlug, $plugin['category'] ?? null);
        $nextSteps = $this->getPluginNextSteps($pluginSlug, $plugin);

        return [
            'success' => true,
            'license' => $license,
            'plugin' => [
                'name' => $plugin['name'],
                'slug' => $plugin['slug'],
                'description' => $plugin['description'],
                'category' => $plugin['category'],
            ],
            'features_unlocked' => $features,
            'settings_path' => $settingsPath,
            'next_steps' => $nextSteps,
        ];
    }

    /**
     * Parse features from JSON or array.
     */
    private function parseFeatures(mixed $features): array
    {
        if (is_string($features)) {
            return json_decode($features, true) ?? [];
        }
        return is_array($features) ? $features : [];
    }

    /**
     * Get the settings path for a plugin.
     */
    private function getPluginSettingsPath(string $slug, ?string $category): ?string
    {
        // Integration plugins go to integrations settings with provider highlighted
        if (str_starts_with($slug, 'integration-')) {
            $provider = str_replace('integration-', '', $slug);
            return "/settings/integrations?provider={$provider}";
        }

        // Plugin-specific paths
        $pluginPaths = [
            'forecasting-basic' => '/forecasting',
            'forecasting-pro' => '/forecasting',
            'quotes-invoices' => '/quotes',
            'quotes-view' => '/quotes',
            'duplicate-detection' => '/settings/data-management',
            'deal-rotting' => '/settings/pipelines',
            'web-forms-basic' => '/web-forms',
            'web-forms-pro' => '/web-forms',
            'blueprints-basic' => '/blueprints',
            'blueprints-pro' => '/blueprints',
            'workflows-advanced' => '/workflows',
            'time-machine' => '/time-machine',
            'scenario-planner' => '/forecasting/scenarios',
            'revenue-graph' => '/analytics/revenue-graph',
            'deal-rooms' => '/deal-rooms',
            'competitor-battlecards' => '/competitors',
            'whatsapp-integration' => '/settings/communication?tab=whatsapp',
            'live-chat' => '/settings/communication?tab=chat',
            'scheduling' => '/scheduling',
            'email-sequences' => '/cadences',
            'ai-assistant' => '/settings/ai',
            'ai-insights' => '/settings/ai',
        ];

        if (isset($pluginPaths[$slug])) {
            return $pluginPaths[$slug];
        }

        // Category-based fallback paths
        return match ($category) {
            'communication' => '/settings/communication',
            'marketing' => '/marketing',
            'analytics' => '/analytics',
            'documents' => '/documents',
            'ai' => '/settings/ai',
            'sales' => '/settings/billing/plugins',
            default => '/settings/billing/plugins',
        };
    }

    /**
     * Get next steps after activating a plugin.
     */
    private function getPluginNextSteps(string $slug, array $plugin): array
    {
        $steps = [];
        $settingsPath = $this->getPluginSettingsPath($slug, $plugin['category'] ?? null);

        // Integration plugins need to be connected
        if (str_starts_with($slug, 'integration-')) {
            $provider = ucfirst(str_replace('integration-', '', $slug));
            $steps[] = [
                'title' => "Connect your {$provider} account",
                'description' => "Authorize access to your {$provider} account to enable data synchronization.",
                'action' => 'Connect Account',
                'path' => $settingsPath,
            ];
        } else {
            // Non-integration plugins - provide guidance based on category
            $actionText = match ($plugin['category'] ?? '') {
                'analytics' => 'View your new analytics dashboards and reports.',
                'ai' => 'Configure AI settings and start using intelligent features.',
                'documents' => 'Create and manage documents with your new capabilities.',
                'communication' => 'Set up your communication channels.',
                'marketing' => 'Start building campaigns with new marketing tools.',
                default => 'Explore your new features and capabilities.',
            };

            if ($settingsPath !== '/settings/billing/plugins') {
                $steps[] = [
                    'title' => 'Get started',
                    'description' => $actionText,
                    'action' => 'Open Feature',
                    'path' => $settingsPath,
                ];
            }
        }

        // Add feature list if available
        $features = $this->parseFeatures($plugin['features'] ?? null);
        if (!empty($features)) {
            $featureList = implode(', ', array_slice($features, 0, 3));
            if (count($features) > 3) {
                $featureList .= ', and ' . (count($features) - 3) . ' more';
            }

            $steps[] = [
                'title' => 'Features now available',
                'description' => $featureList,
                'action' => null,
                'path' => null,
            ];
        }

        return $steps;
    }

    /**
     * Deactivate a plugin license.
     *
     * @return array{success: bool, errors?: string[]}
     */
    public function deactivatePlugin(string $pluginSlug): array
    {
        $license = $this->repository->getLicenseForPlugin($pluginSlug);

        if (!$license) {
            return [
                'success' => false,
                'errors' => ['No active license found'],
            ];
        }

        if (!empty($license['bundle_slug'])) {
            return [
                'success' => false,
                'errors' => ['Cannot deactivate a bundled plugin individually'],
            ];
        }

        $result = $this->repository->deactivatePlugin($pluginSlug);

        if ($result) {
            $this->clearCache();
        }

        return [
            'success' => $result,
            'errors' => $result ? [] : ['Failed to deactivate plugin'],
        ];
    }

    /**
     * Cancel a license by ID.
     */
    public function cancelLicense(int $licenseId): array
    {
        $license = $this->repository->findLicenseById($licenseId);

        if (!$license) {
            return [
                'success' => false,
                'errors' => ['License not found'],
            ];
        }

        if (!empty($license['bundle_slug'])) {
            return [
                'success' => false,
                'errors' => ['Cannot cancel a bundled license individually'],
            ];
        }

        $result = $this->repository->cancelLicense($licenseId);
        $this->clearCache();

        return [
            'success' => true,
            'license' => $result,
        ];
    }

    /**
     * Reactivate a cancelled license.
     */
    public function reactivateLicense(int $licenseId, ?\DateTimeInterface $expiresAt = null): array
    {
        $license = $this->repository->findLicenseById($licenseId);

        if (!$license) {
            return [
                'success' => false,
                'errors' => ['License not found'],
            ];
        }

        if ($license['status'] === LicenseStatus::ACTIVE) {
            return [
                'success' => false,
                'errors' => ['License is already active'],
            ];
        }

        $result = $this->repository->reactivateLicense($licenseId, $expiresAt);
        $this->clearCache();

        return [
            'success' => true,
            'license' => $result,
        ];
    }

    // =========================================================================
    // QUERY USE CASES - USAGE
    // =========================================================================

    public function getPluginUsage(string $pluginSlug, string $metric): ?array
    {
        return $this->repository->getPluginUsage($pluginSlug, $metric);
    }

    public function listPluginUsage(): array
    {
        return $this->repository->listPluginUsage();
    }

    public function checkUsageLimit(string $metric): array
    {
        $currentPlan = $this->getCurrentPlan();
        $limit = $this->usageService->getMetricLimit($currentPlan, $metric);

        $usage = $this->repository->getPluginUsage('core', $metric);
        $used = $usage['quantity'] ?? 0;

        return $this->usageService->calculateUsageMetrics($used, $limit);
    }

    public function getUsageStats(): array
    {
        $currentPlan = $this->getCurrentPlan();
        $limits = $this->usageService->getLimitsForPlan($currentPlan);
        $stats = [];

        foreach (array_keys($limits) as $metric) {
            $stats[$metric] = $this->checkUsageLimit($metric);
        }

        return $stats;
    }

    // =========================================================================
    // COMMAND USE CASES - USAGE
    // =========================================================================

    public function trackUsage(string $metric, int $amount = 1): array
    {
        $currentPlan = $this->getCurrentPlan();
        $limit = $this->usageService->getMetricLimit($currentPlan, $metric);

        return $this->repository->trackUsage('core', $metric, $amount, $limit);
    }

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    public function getPluginStats(): array
    {
        return $this->repository->getPluginStats();
    }

    public function getLicenseStats(): array
    {
        return $this->repository->getLicenseStats();
    }

    public function getPluginRecommendations(int $limit = 5): array
    {
        return $this->repository->getPluginRecommendations($limit);
    }

    public function getExpiringLicenses(int $days = 30): array
    {
        return $this->repository->getExpiringLicenses($days);
    }

    /**
     * Get complete license state for API response.
     */
    public function getLicenseState(): array
    {
        $currentPlan = $this->getCurrentPlan();

        return [
            'plan' => $currentPlan,
            'plugins' => $this->getLicensedPluginSlugs(),
            'usage' => $this->getUsageStats(),
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    public function clearCache(): void
    {
        Cache::forget('licensed_plugins');

        // Clear plugin-specific caches
        $plugins = $this->repository->findAll();
        foreach ($plugins as $plugin) {
            Cache::forget("plugin_license:{$plugin['slug']}");
        }
    }

    private function getCurrentPlan(): string
    {
        // This would typically come from the tenant subscription
        // For now, we'll use a default or check the auth context
        return 'professional'; // Default to professional for development
    }
}
