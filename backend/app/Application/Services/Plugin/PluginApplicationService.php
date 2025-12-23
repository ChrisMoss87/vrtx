<?php

declare(strict_types=1);

namespace App\Application\Services\Plugin;

use App\Domain\Plugin\Repositories\PluginRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;

class PluginApplicationService
{
    public function __construct(
        private PluginRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES - PLUGIN CATALOG (Central DB)
    // =========================================================================

    /**
     * List plugins from the catalog.
     */
    public function listPlugins(array $filters = []): array
    {
        return $this->repository->listPlugins($filters);
    }

    /**
     * Get a single plugin by ID.
     */
    public function getPlugin(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Get a plugin by slug.
     */
    public function getPluginBySlug(string $slug): ?array
    {
        return $this->repository->findBySlug($slug);
    }

    /**
     * Get plugins by category.
     */
    public function getPluginsByCategory(string $category): array
    {
        return $this->repository->getPluginsByCategory($category);
    }

    /**
     * Get available plugins (active in catalog).
     */
    public function getAvailablePlugins(): array
    {
        return $this->repository->getAvailablePlugins();
    }

    // =========================================================================
    // COMMAND USE CASES - PLUGIN CATALOG (Central DB)
    // =========================================================================

    /**
     * Create a new plugin in the catalog.
     */
    public function createPlugin(array $data): array
    {
        return $this->repository->createPlugin($data);
    }

    /**
     * Update a plugin in the catalog.
     */
    public function updatePlugin(int $id, array $data): array
    {
        return $this->repository->updatePlugin($id, $data);
    }

    /**
     * Delete a plugin from the catalog.
     */
    public function deletePlugin(int $id): bool
    {
        return $this->repository->deletePlugin($id);
    }

    // =========================================================================
    // QUERY USE CASES - PLUGIN BUNDLES (Central DB)
    // =========================================================================

    /**
     * List plugin bundles.
     */
    public function listBundles(array $filters = []): array
    {
        return $this->repository->listBundles($filters);
    }

    /**
     * Get a single bundle by ID.
     */
    public function getBundle(int $id): ?array
    {
        return $this->repository->findBundleById($id);
    }

    /**
     * Get a bundle by slug.
     */
    public function getBundleBySlug(string $slug): ?array
    {
        return $this->repository->findBundleBySlug($slug);
    }

    /**
     * Get active bundles.
     */
    public function getActiveBundles(): array
    {
        return $this->repository->getActiveBundles();
    }

    // =========================================================================
    // COMMAND USE CASES - PLUGIN BUNDLES (Central DB)
    // =========================================================================

    /**
     * Create a new plugin bundle.
     */
    public function createBundle(array $data): array
    {
        return $this->repository->createBundle($data);
    }

    /**
     * Update a plugin bundle.
     */
    public function updateBundle(int $id, array $data): array
    {
        return $this->repository->updateBundle($id, $data);
    }

    /**
     * Delete a plugin bundle.
     */
    public function deleteBundle(int $id): bool
    {
        return $this->repository->deleteBundle($id);
    }

    // =========================================================================
    // QUERY USE CASES - PLUGIN LICENSES (Tenant DB)
    // =========================================================================

    /**
     * List plugin licenses for the current tenant.
     */
    public function listLicenses(array $filters = []): array
    {
        return $this->repository->listLicenses($filters);
    }

    /**
     * Get a single license by ID.
     */
    public function getLicense(int $id): ?array
    {
        return $this->repository->findLicenseById($id);
    }

    /**
     * Get license for a specific plugin.
     */
    public function getLicenseForPlugin(string $pluginSlug): ?array
    {
        return $this->repository->getLicenseForPlugin($pluginSlug);
    }

    /**
     * Get all active licenses.
     */
    public function getActiveLicenses(): array
    {
        return $this->repository->getActiveLicenses();
    }

    /**
     * Get installed/licensed plugins for tenant.
     */
    public function getInstalledPlugins(): array
    {
        return $this->repository->getInstalledPlugins();
    }

    /**
     * Check if a plugin is licensed/installed.
     */
    public function isPluginInstalled(string $pluginSlug): bool
    {
        return $this->repository->isPluginInstalled($pluginSlug);
    }

    // =========================================================================
    // COMMAND USE CASES - PLUGIN LICENSES (Tenant DB)
    // =========================================================================

    /**
     * Activate/install a plugin license.
     */
    public function activatePlugin(array $data): array
    {
        return $this->repository->activatePlugin($data);
    }

    /**
     * Update a plugin license.
     */
    public function updateLicense(int $id, array $data): array
    {
        return $this->repository->updateLicense($id, $data);
    }

    /**
     * Deactivate/uninstall a plugin.
     */
    public function deactivatePlugin(string $pluginSlug): bool
    {
        return $this->repository->deactivatePlugin($pluginSlug);
    }

    /**
     * Cancel a license.
     */
    public function cancelLicense(int $id): array
    {
        return $this->repository->cancelLicense($id);
    }

    /**
     * Reactivate a cancelled license.
     */
    public function reactivateLicense(int $id, ?\DateTimeInterface $expiresAt = null): array
    {
        return $this->repository->reactivateLicense($id, $expiresAt);
    }

    // =========================================================================
    // QUERY USE CASES - PLUGIN USAGE (Tenant DB)
    // =========================================================================

    /**
     * Get usage for a plugin and metric.
     */
    public function getPluginUsage(string $pluginSlug, string $metric): ?array
    {
        return $this->repository->getPluginUsage($pluginSlug, $metric);
    }

    /**
     * Get all usage metrics for a plugin.
     */
    public function getPluginUsageMetrics(string $pluginSlug): array
    {
        return $this->repository->getPluginUsageMetrics($pluginSlug);
    }

    /**
     * List usage for all plugins in current period.
     */
    public function listPluginUsage(): array
    {
        return $this->repository->listPluginUsage();
    }

    /**
     * Check if usage limit is reached.
     */
    public function isUsageLimitReached(string $pluginSlug, string $metric): bool
    {
        return $this->repository->isUsageLimitReached($pluginSlug, $metric);
    }

    // =========================================================================
    // COMMAND USE CASES - PLUGIN USAGE (Tenant DB)
    // =========================================================================

    /**
     * Track/increment plugin usage.
     */
    public function trackUsage(string $pluginSlug, string $metric, int $amount = 1, ?int $limit = null, ?float $overageRate = null): array
    {
        return $this->repository->trackUsage($pluginSlug, $metric, $amount, $limit, $overageRate);
    }

    /**
     * Reset usage for a plugin metric (new period).
     */
    public function resetUsage(string $pluginSlug, string $metric): array
    {
        return $this->repository->resetUsage($pluginSlug, $metric);
    }

    /**
     * Update usage limits.
     */
    public function updateUsageLimits(string $pluginSlug, string $metric, ?int $limitQuantity, ?float $overageRate = null): ?array
    {
        return $this->repository->updateUsageLimits($pluginSlug, $metric, $limitQuantity, $overageRate);
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get plugin statistics.
     */
    public function getPluginStats(): array
    {
        return $this->repository->getPluginStats();
    }

    /**
     * Get license statistics for tenant.
     */
    public function getLicenseStats(): array
    {
        return $this->repository->getLicenseStats();
    }

    /**
     * Get usage statistics for all plugins.
     */
    public function getUsageStats(): array
    {
        return $this->repository->getUsageStats();
    }

    /**
     * Get detailed usage for a specific plugin.
     */
    public function getPluginUsageReport(string $pluginSlug, int $months = 3): array
    {
        return $this->repository->getPluginUsageReport($pluginSlug, $months);
    }

    /**
     * Get plugin recommendations based on usage and features.
     */
    public function getPluginRecommendations(int $limit = 5): array
    {
        return $this->repository->getPluginRecommendations($limit);
    }

    /**
     * Get expiring licenses.
     */
    public function getExpiringLicenses(int $days = 30): array
    {
        return $this->repository->getExpiringLicenses($days);
    }
}
