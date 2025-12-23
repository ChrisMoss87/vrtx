<?php

declare(strict_types=1);

namespace App\Domain\Plugin\Repositories;

use App\Domain\Plugin\Entities\Plugin;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface PluginRepositoryInterface
{
    // =========================================================================
    // BASIC CRUD
    // =========================================================================

    public function findById(int $id): ?Plugin;

    public function findByIdAsArray(int $id): ?array;

    public function findBySlug(string $slug): ?array;

    public function findAll(): array;

    public function save(Plugin $entity): Plugin;

    public function delete(int $id): bool;

    // =========================================================================
    // PLUGIN CATALOG QUERIES
    // =========================================================================

    /**
     * List plugins with filters.
     *
     * @param array $filters Supported keys: active, category, tier, search
     * @return array
     */
    public function listPlugins(array $filters = []): array;

    /**
     * Get plugins by category.
     */
    public function getPluginsByCategory(string $category): array;

    /**
     * Get available plugins (active in catalog).
     */
    public function getAvailablePlugins(): array;

    // =========================================================================
    // PLUGIN BUNDLE QUERIES
    // =========================================================================

    /**
     * List plugin bundles with filters.
     *
     * @param array $filters Supported keys: active
     * @return array
     */
    public function listBundles(array $filters = []): array;

    /**
     * Get a single bundle by ID.
     */
    public function findBundleById(int $id): ?array;

    /**
     * Get a bundle by slug.
     */
    public function findBundleBySlug(string $slug): ?array;

    /**
     * Get active bundles.
     */
    public function getActiveBundles(): array;

    // =========================================================================
    // PLUGIN LICENSE QUERIES (TENANT DB)
    // =========================================================================

    /**
     * List plugin licenses for the current tenant.
     *
     * @param array $filters Supported keys: status, active, plugin_slug
     * @return array
     */
    public function listLicenses(array $filters = []): array;

    /**
     * Get a single license by ID.
     */
    public function findLicenseById(int $id): ?array;

    /**
     * Get license for a specific plugin.
     */
    public function getLicenseForPlugin(string $pluginSlug): ?array;

    /**
     * Get all active licenses.
     */
    public function getActiveLicenses(): array;

    /**
     * Get installed/licensed plugins for tenant.
     */
    public function getInstalledPlugins(): array;

    /**
     * Check if a plugin is licensed/installed.
     */
    public function isPluginInstalled(string $pluginSlug): bool;

    // =========================================================================
    // PLUGIN USAGE QUERIES (TENANT DB)
    // =========================================================================

    /**
     * Get usage for a plugin and metric.
     */
    public function getPluginUsage(string $pluginSlug, string $metric): ?array;

    /**
     * Get current usage count for a plugin metric in the current period.
     */
    public function getCurrentUsage(string $pluginSlug, string $metric, string $period = 'monthly'): int;

    /**
     * Get all usage metrics for a plugin.
     */
    public function getPluginUsageMetrics(string $pluginSlug): array;

    /**
     * List usage for all plugins in current period.
     */
    public function listPluginUsage(): array;

    /**
     * Check if usage limit is reached.
     */
    public function isUsageLimitReached(string $pluginSlug, string $metric): bool;

    // =========================================================================
    // PLUGIN CATALOG COMMANDS
    // =========================================================================

    /**
     * Create a new plugin in the catalog.
     */
    public function createPlugin(array $data): array;

    /**
     * Update a plugin in the catalog.
     */
    public function updatePlugin(int $id, array $data): array;

    /**
     * Delete a plugin from the catalog.
     */
    public function deletePlugin(int $id): bool;

    // =========================================================================
    // PLUGIN BUNDLE COMMANDS
    // =========================================================================

    /**
     * Create a new plugin bundle.
     */
    public function createBundle(array $data): array;

    /**
     * Update a plugin bundle.
     */
    public function updateBundle(int $id, array $data): array;

    /**
     * Delete a plugin bundle.
     */
    public function deleteBundle(int $id): bool;

    // =========================================================================
    // PLUGIN LICENSE COMMANDS (TENANT DB)
    // =========================================================================

    /**
     * Activate/install a plugin license.
     */
    public function activatePlugin(array $data): array;

    /**
     * Update a plugin license.
     */
    public function updateLicense(int $id, array $data): array;

    /**
     * Deactivate/uninstall a plugin.
     */
    public function deactivatePlugin(string $pluginSlug): bool;

    /**
     * Cancel a license.
     */
    public function cancelLicense(int $id): array;

    /**
     * Reactivate a cancelled license.
     */
    public function reactivateLicense(int $id, ?\DateTimeInterface $expiresAt = null): array;

    // =========================================================================
    // PLUGIN USAGE COMMANDS (TENANT DB)
    // =========================================================================

    /**
     * Track/increment plugin usage.
     */
    public function trackUsage(
        string $pluginSlug,
        string $metric,
        int $amount = 1,
        ?int $limit = null,
        ?float $overageRate = null
    ): array;

    /**
     * Reset usage for a plugin metric (new period).
     */
    public function resetUsage(string $pluginSlug, string $metric): array;

    /**
     * Update usage limits.
     */
    public function updateUsageLimits(
        string $pluginSlug,
        string $metric,
        ?int $limitQuantity,
        ?float $overageRate = null
    ): ?array;

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    /**
     * Get plugin statistics.
     */
    public function getPluginStats(): array;

    /**
     * Get license statistics for tenant.
     */
    public function getLicenseStats(): array;

    /**
     * Get usage statistics for all plugins.
     */
    public function getUsageStats(): array;

    /**
     * Get detailed usage for a specific plugin.
     */
    public function getPluginUsageReport(string $pluginSlug, int $months = 3): array;

    /**
     * Get plugin recommendations based on usage and features.
     */
    public function getPluginRecommendations(int $limit = 5): array;

    /**
     * Get expiring licenses.
     */
    public function getExpiringLicenses(int $days = 30): array;
}
