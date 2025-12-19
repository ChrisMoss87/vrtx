<?php

declare(strict_types=1);

namespace App\Application\Services\Plugin;

use App\Domain\Plugin\Repositories\PluginRepositoryInterface;
use App\Models\Plugin;
use App\Models\PluginBundle;
use App\Models\PluginLicense;
use App\Models\PluginUsage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PluginApplicationService
{
    public function __construct(
        private PluginRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // QUERY USE CASES - PLUGIN CATALOG (Central DB)
    // =========================================================================

    /**
     * List plugins from the catalog.
     */
    public function listPlugins(array $filters = []): Collection
    {
        $query = Plugin::query();

        // Filter by active status
        if (!empty($filters['active'])) {
            $query->active();
        }

        // Filter by category
        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        // Filter by tier
        if (!empty($filters['tier'])) {
            $query->byTier($filters['tier']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('display_order')->orderBy('name')->get();
    }

    /**
     * Get a single plugin by ID.
     */
    public function getPlugin(int $id): ?Plugin
    {
        return Plugin::find($id);
    }

    /**
     * Get a plugin by slug.
     */
    public function getPluginBySlug(string $slug): ?Plugin
    {
        return Plugin::where('slug', $slug)->first();
    }

    /**
     * Get plugins by category.
     */
    public function getPluginsByCategory(string $category): Collection
    {
        return Plugin::byCategory($category)
            ->active()
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Get available plugins (active in catalog).
     */
    public function getAvailablePlugins(): Collection
    {
        return Plugin::active()->orderBy('display_order')->orderBy('name')->get();
    }

    // =========================================================================
    // COMMAND USE CASES - PLUGIN CATALOG (Central DB)
    // =========================================================================

    /**
     * Create a new plugin in the catalog.
     */
    public function createPlugin(array $data): Plugin
    {
        return Plugin::create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'],
            'tier' => $data['tier'] ?? Plugin::TIER_PROFESSIONAL,
            'pricing_model' => $data['pricing_model'] ?? Plugin::PRICING_PER_USER,
            'price_monthly' => $data['price_monthly'] ?? null,
            'price_yearly' => $data['price_yearly'] ?? null,
            'features' => $data['features'] ?? [],
            'requirements' => $data['requirements'] ?? [],
            'limits' => $data['limits'] ?? [],
            'icon' => $data['icon'] ?? null,
            'display_order' => $data['display_order'] ?? 999,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update a plugin in the catalog.
     */
    public function updatePlugin(int $id, array $data): Plugin
    {
        $plugin = Plugin::findOrFail($id);

        $updateData = [];

        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['category'])) $updateData['category'] = $data['category'];
        if (isset($data['tier'])) $updateData['tier'] = $data['tier'];
        if (isset($data['pricing_model'])) $updateData['pricing_model'] = $data['pricing_model'];
        if (isset($data['price_monthly'])) $updateData['price_monthly'] = $data['price_monthly'];
        if (isset($data['price_yearly'])) $updateData['price_yearly'] = $data['price_yearly'];
        if (isset($data['features'])) $updateData['features'] = $data['features'];
        if (isset($data['requirements'])) $updateData['requirements'] = $data['requirements'];
        if (isset($data['limits'])) $updateData['limits'] = $data['limits'];
        if (isset($data['icon'])) $updateData['icon'] = $data['icon'];
        if (isset($data['display_order'])) $updateData['display_order'] = $data['display_order'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];

        $plugin->update($updateData);

        return $plugin->fresh();
    }

    /**
     * Delete a plugin from the catalog.
     */
    public function deletePlugin(int $id): bool
    {
        $plugin = Plugin::findOrFail($id);
        return $plugin->delete();
    }

    // =========================================================================
    // QUERY USE CASES - PLUGIN BUNDLES (Central DB)
    // =========================================================================

    /**
     * List plugin bundles.
     */
    public function listBundles(array $filters = []): Collection
    {
        $query = PluginBundle::query();

        if (!empty($filters['active'])) {
            $query->active();
        }

        return $query->orderBy('display_order')->orderBy('name')->get();
    }

    /**
     * Get a single bundle by ID.
     */
    public function getBundle(int $id): ?PluginBundle
    {
        return PluginBundle::find($id);
    }

    /**
     * Get a bundle by slug.
     */
    public function getBundleBySlug(string $slug): ?PluginBundle
    {
        return PluginBundle::where('slug', $slug)->first();
    }

    /**
     * Get active bundles.
     */
    public function getActiveBundles(): Collection
    {
        return PluginBundle::active()->orderBy('display_order')->orderBy('name')->get();
    }

    // =========================================================================
    // COMMAND USE CASES - PLUGIN BUNDLES (Central DB)
    // =========================================================================

    /**
     * Create a new plugin bundle.
     */
    public function createBundle(array $data): PluginBundle
    {
        return PluginBundle::create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'plugins' => $data['plugins'], // Array of plugin slugs
            'price_monthly' => $data['price_monthly'],
            'price_yearly' => $data['price_yearly'],
            'discount_percent' => $data['discount_percent'] ?? null,
            'icon' => $data['icon'] ?? null,
            'display_order' => $data['display_order'] ?? 999,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update a plugin bundle.
     */
    public function updateBundle(int $id, array $data): PluginBundle
    {
        $bundle = PluginBundle::findOrFail($id);

        $updateData = [];

        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['plugins'])) $updateData['plugins'] = $data['plugins'];
        if (isset($data['price_monthly'])) $updateData['price_monthly'] = $data['price_monthly'];
        if (isset($data['price_yearly'])) $updateData['price_yearly'] = $data['price_yearly'];
        if (isset($data['discount_percent'])) $updateData['discount_percent'] = $data['discount_percent'];
        if (isset($data['icon'])) $updateData['icon'] = $data['icon'];
        if (isset($data['display_order'])) $updateData['display_order'] = $data['display_order'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];

        $bundle->update($updateData);

        return $bundle->fresh();
    }

    /**
     * Delete a plugin bundle.
     */
    public function deleteBundle(int $id): bool
    {
        $bundle = PluginBundle::findOrFail($id);
        return $bundle->delete();
    }

    // =========================================================================
    // QUERY USE CASES - PLUGIN LICENSES (Tenant DB)
    // =========================================================================

    /**
     * List plugin licenses for the current tenant.
     */
    public function listLicenses(array $filters = []): Collection
    {
        $query = PluginLicense::query();

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter active only
        if (!empty($filters['active'])) {
            $query->active();
        }

        // Filter by plugin slug
        if (!empty($filters['plugin_slug'])) {
            $query->forPlugin($filters['plugin_slug']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get a single license by ID.
     */
    public function getLicense(int $id): ?PluginLicense
    {
        return PluginLicense::find($id);
    }

    /**
     * Get license for a specific plugin.
     */
    public function getLicenseForPlugin(string $pluginSlug): ?PluginLicense
    {
        return PluginLicense::forPlugin($pluginSlug)->active()->first();
    }

    /**
     * Get all active licenses.
     */
    public function getActiveLicenses(): Collection
    {
        return PluginLicense::active()->get();
    }

    /**
     * Get installed/licensed plugins for tenant.
     */
    public function getInstalledPlugins(): Collection
    {
        $licenses = PluginLicense::active()->get();

        return $licenses->map(function ($license) {
            $plugin = $license->getPlugin();
            return $plugin ? array_merge($plugin->toArray(), [
                'license_id' => $license->id,
                'license_status' => $license->status,
                'license_expires_at' => $license->expires_at,
            ]) : null;
        })->filter();
    }

    /**
     * Check if a plugin is licensed/installed.
     */
    public function isPluginInstalled(string $pluginSlug): bool
    {
        return PluginLicense::forPlugin($pluginSlug)->active()->exists();
    }

    // =========================================================================
    // COMMAND USE CASES - PLUGIN LICENSES (Tenant DB)
    // =========================================================================

    /**
     * Activate/install a plugin license.
     */
    public function activatePlugin(array $data): PluginLicense
    {
        return PluginLicense::create([
            'plugin_slug' => $data['plugin_slug'],
            'bundle_slug' => $data['bundle_slug'] ?? null,
            'status' => PluginLicense::STATUS_ACTIVE,
            'pricing_model' => $data['pricing_model'] ?? Plugin::PRICING_PER_USER,
            'quantity' => $data['quantity'] ?? 1,
            'price_monthly' => $data['price_monthly'] ?? null,
            'external_subscription_item_id' => $data['external_subscription_item_id'] ?? null,
            'activated_at' => now(),
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }

    /**
     * Update a plugin license.
     */
    public function updateLicense(int $id, array $data): PluginLicense
    {
        $license = PluginLicense::findOrFail($id);

        $updateData = [];

        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['quantity'])) $updateData['quantity'] = $data['quantity'];
        if (isset($data['price_monthly'])) $updateData['price_monthly'] = $data['price_monthly'];
        if (isset($data['external_subscription_item_id'])) $updateData['external_subscription_item_id'] = $data['external_subscription_item_id'];
        if (isset($data['expires_at'])) $updateData['expires_at'] = $data['expires_at'];

        $license->update($updateData);

        return $license->fresh();
    }

    /**
     * Deactivate/uninstall a plugin.
     */
    public function deactivatePlugin(string $pluginSlug): bool
    {
        $license = PluginLicense::forPlugin($pluginSlug)->active()->first();

        if (!$license) {
            return false;
        }

        $license->update([
            'status' => PluginLicense::STATUS_CANCELLED,
        ]);

        return true;
    }

    /**
     * Cancel a license.
     */
    public function cancelLicense(int $id): PluginLicense
    {
        $license = PluginLicense::findOrFail($id);

        $license->update([
            'status' => PluginLicense::STATUS_CANCELLED,
        ]);

        return $license->fresh();
    }

    /**
     * Reactivate a cancelled license.
     */
    public function reactivateLicense(int $id, ?\DateTimeInterface $expiresAt = null): PluginLicense
    {
        $license = PluginLicense::findOrFail($id);

        $license->update([
            'status' => PluginLicense::STATUS_ACTIVE,
            'activated_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        return $license->fresh();
    }

    // =========================================================================
    // QUERY USE CASES - PLUGIN USAGE (Tenant DB)
    // =========================================================================

    /**
     * Get usage for a plugin and metric.
     */
    public function getPluginUsage(string $pluginSlug, string $metric): ?PluginUsage
    {
        return PluginUsage::forPlugin($pluginSlug)
            ->forMetric($metric)
            ->currentPeriod()
            ->first();
    }

    /**
     * Get all usage metrics for a plugin.
     */
    public function getPluginUsageMetrics(string $pluginSlug): Collection
    {
        return PluginUsage::forPlugin($pluginSlug)
            ->currentPeriod()
            ->get();
    }

    /**
     * List usage for all plugins in current period.
     */
    public function listPluginUsage(): Collection
    {
        return PluginUsage::currentPeriod()->get();
    }

    /**
     * Check if usage limit is reached.
     */
    public function isUsageLimitReached(string $pluginSlug, string $metric): bool
    {
        $usage = $this->getPluginUsage($pluginSlug, $metric);
        return $usage ? $usage->isLimitReached() : false;
    }

    // =========================================================================
    // COMMAND USE CASES - PLUGIN USAGE (Tenant DB)
    // =========================================================================

    /**
     * Track/increment plugin usage.
     */
    public function trackUsage(string $pluginSlug, string $metric, int $amount = 1, ?int $limit = null, ?float $overageRate = null): PluginUsage
    {
        $usage = PluginUsage::getOrCreateForPeriod($pluginSlug, $metric, $limit, $overageRate);
        $usage->incrementUsage($amount);
        return $usage->fresh();
    }

    /**
     * Reset usage for a plugin metric (new period).
     */
    public function resetUsage(string $pluginSlug, string $metric): PluginUsage
    {
        return PluginUsage::create([
            'plugin_slug' => $pluginSlug,
            'metric' => $metric,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'quantity' => 0,
        ]);
    }

    /**
     * Update usage limits.
     */
    public function updateUsageLimits(string $pluginSlug, string $metric, ?int $limitQuantity, ?float $overageRate = null): ?PluginUsage
    {
        $usage = $this->getPluginUsage($pluginSlug, $metric);

        if (!$usage) {
            return null;
        }

        $usage->update([
            'limit_quantity' => $limitQuantity,
            'overage_rate' => $overageRate,
        ]);

        return $usage->fresh();
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get plugin statistics.
     */
    public function getPluginStats(): array
    {
        $totalPlugins = Plugin::active()->count();
        $installedCount = PluginLicense::active()->count();

        $byCategory = Plugin::active()
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $byTier = Plugin::active()
            ->selectRaw('tier, COUNT(*) as count')
            ->groupBy('tier')
            ->pluck('count', 'tier')
            ->toArray();

        return [
            'total_available' => $totalPlugins,
            'total_installed' => $installedCount,
            'by_category' => $byCategory,
            'by_tier' => $byTier,
        ];
    }

    /**
     * Get license statistics for tenant.
     */
    public function getLicenseStats(): array
    {
        $total = PluginLicense::count();
        $active = PluginLicense::active()->count();
        $expired = PluginLicense::where('status', PluginLicense::STATUS_EXPIRED)->count();
        $cancelled = PluginLicense::where('status', PluginLicense::STATUS_CANCELLED)->count();

        $expiringQuery = PluginLicense::active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30));

        $expiringSoon = $expiringQuery->count();

        $totalSpend = PluginLicense::active()->sum('price_monthly');

        return [
            'total_licenses' => $total,
            'active' => $active,
            'expired' => $expired,
            'cancelled' => $cancelled,
            'expiring_soon' => $expiringSoon,
            'monthly_spend' => $totalSpend,
        ];
    }

    /**
     * Get usage statistics for all plugins.
     */
    public function getUsageStats(): array
    {
        $currentUsage = PluginUsage::currentPeriod()->get();

        $byPlugin = $currentUsage->groupBy('plugin_slug')->map(function ($usages, $pluginSlug) {
            return [
                'plugin_slug' => $pluginSlug,
                'metrics' => $usages->map(function ($usage) {
                    return [
                        'metric' => $usage->metric,
                        'quantity' => $usage->quantity,
                        'limit' => $usage->limit_quantity,
                        'remaining' => $usage->remaining,
                        'usage_percent' => $usage->usage_percent,
                        'overage' => $usage->overage,
                        'overage_cost' => $usage->overage_cost,
                    ];
                })->keyBy('metric'),
            ];
        })->values();

        $totalOverage = $currentUsage->sum('overage_cost');
        $limitReached = $currentUsage->filter(fn($u) => $u->isLimitReached())->count();

        return [
            'by_plugin' => $byPlugin,
            'total_overage_cost' => $totalOverage,
            'limits_reached' => $limitReached,
        ];
    }

    /**
     * Get detailed usage for a specific plugin.
     */
    public function getPluginUsageReport(string $pluginSlug, int $months = 3): array
    {
        $plugin = $this->getPluginBySlug($pluginSlug);
        $license = $this->getLicenseForPlugin($pluginSlug);

        $historicalUsage = PluginUsage::forPlugin($pluginSlug)
            ->where('period_start', '>=', now()->subMonths($months)->startOfMonth())
            ->orderBy('period_start', 'desc')
            ->get();

        $currentPeriodUsage = PluginUsage::forPlugin($pluginSlug)
            ->currentPeriod()
            ->get();

        return [
            'plugin' => $plugin,
            'license' => $license,
            'current_period' => $currentPeriodUsage->map(function ($usage) {
                return [
                    'metric' => $usage->metric,
                    'quantity' => $usage->quantity,
                    'limit' => $usage->limit_quantity,
                    'remaining' => $usage->remaining,
                    'usage_percent' => $usage->usage_percent,
                    'overage' => $usage->overage,
                    'overage_cost' => $usage->overage_cost,
                ];
            }),
            'historical' => $historicalUsage->groupBy(function ($usage) {
                return $usage->period_start->format('Y-m');
            })->map(function ($usages, $period) {
                return [
                    'period' => $period,
                    'metrics' => $usages->map(function ($usage) {
                        return [
                            'metric' => $usage->metric,
                            'quantity' => $usage->quantity,
                            'limit' => $usage->limit_quantity,
                            'overage' => $usage->overage,
                            'overage_cost' => $usage->overage_cost,
                        ];
                    })->keyBy('metric'),
                ];
            }),
        ];
    }

    /**
     * Get plugin recommendations based on usage and features.
     */
    public function getPluginRecommendations(int $limit = 5): Collection
    {
        $installedSlugs = PluginLicense::active()->pluck('plugin_slug')->toArray();

        // Get plugins not yet installed
        $available = Plugin::active()
            ->whereNotIn('slug', $installedSlugs)
            ->orderBy('display_order')
            ->limit($limit)
            ->get();

        return $available;
    }

    /**
     * Get expiring licenses.
     */
    public function getExpiringLicenses(int $days = 30): Collection
    {
        return PluginLicense::active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->orderBy('expires_at')
            ->get();
    }
}
