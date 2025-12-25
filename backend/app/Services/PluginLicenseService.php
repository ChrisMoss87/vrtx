<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PluginLicenseService
{
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Plugins included in each plan (cumulative - each plan includes previous)
     */
    private const PLAN_PLUGINS = [
        TenantSubscription::PLAN_FREE => [
            'core-modules',
            'core-datatable',
            'core-kanban',
            'core-dashboards',
            'core-workflows-basic',
        ],
        TenantSubscription::PLAN_STARTER => [
            'core-reports',
            'core-email',
            'core-import-export',
        ],
        TenantSubscription::PLAN_PROFESSIONAL => [
            'forecasting-basic',
            'quotes-view',
            'web-forms-basic',
            'blueprints-basic',
        ],
        TenantSubscription::PLAN_BUSINESS => [
            'forecasting-pro',
            'quotes-invoices',
            'duplicate-detection',
            'deal-rotting',
            'web-forms-pro',
            'workflows-advanced',
            'blueprints-pro',
        ],
        TenantSubscription::PLAN_ENTERPRISE => [
            'time-machine',
            'scenario-planner',
            'revenue-graph',
            'deal-rooms',
            'competitor-battlecards',
            'process-recorder',
            'api-unlimited',
        ],
    ];

    /**
     * Usage limits by plan
     */
    private const PLAN_LIMITS = [
        TenantSubscription::PLAN_FREE => [
            'records' => 500,
            'storage_mb' => 1024, // 1GB
            'api_calls' => 1000,
            'workflows' => 5,
            'blueprints' => 3,
        ],
        TenantSubscription::PLAN_STARTER => [
            'records' => 10000,
            'storage_mb' => 5120, // 5GB
            'api_calls' => 2500,
            'workflows' => 1,
            'blueprints' => 1,
        ],
        TenantSubscription::PLAN_PROFESSIONAL => [
            'records' => 100000,
            'storage_mb' => 25600, // 25GB
            'api_calls' => 5000,
            'workflows' => 10,
            'blueprints' => 5,
        ],
        TenantSubscription::PLAN_BUSINESS => [
            'records' => null, // Unlimited
            'storage_mb' => 102400, // 100GB
            'api_calls' => 25000,
            'workflows' => null,
            'blueprints' => null,
        ],
        TenantSubscription::PLAN_ENTERPRISE => [
            'records' => null,
            'storage_mb' => null,
            'api_calls' => null,
            'workflows' => null,
            'blueprints' => null,
        ],
    ];

    /**
     * Get current subscription
     */
    public function getSubscription(): ?TenantSubscription
    {
        return Cache::remember('tenant_subscription', self::CACHE_TTL, function () {
            return DB::table('tenant_subscriptions')->first();
        });
    }

    /**
     * Get current plan
     */
    public function getCurrentPlan(): string
    {
        return $this->getSubscription()?->plan ?? TenantSubscription::PLAN_FREE;
    }

    /**
     * Check if a plugin is licensed for the current tenant
     */
    public function hasPlugin(string $pluginSlug): bool
    {
        $cacheKey = "plugin_license:{$pluginSlug}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($pluginSlug) {
            // Check if included in base plan
            if ($this->isIncludedInPlan($pluginSlug)) {
                return true;
            }

            // Check for active license
            return DB::table('plugin_licenses')->where('plugin_slug', $pluginSlug)
                ->where('status', PluginLicense::STATUS_ACTIVE)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->exists();
        });
    }

    /**
     * Check if a feature is enabled
     */
    public function hasFeature(string $featureKey): bool
    {
        $cacheKey = "feature_flag:{$featureKey}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($featureKey) {
            $flag = DB::table('feature_flags')->where('feature_key', $featureKey)->first();

            if (!$flag) {
                return false;
            }

            // Check if tenant has override enabled
            if ($flag->is_enabled) {
                return true;
            }

            // Check if plugin is licensed
            if ($flag->plugin_slug && !$this->hasPlugin($flag->plugin_slug)) {
                return false;
            }

            // Check plan requirement
            if ($flag->plan_required && !$this->hasPlan($flag->plan_required)) {
                return false;
            }

            return true;
        });
    }

    /**
     * Check if current plan meets or exceeds required plan
     */
    public function hasPlan(string $requiredPlan): bool
    {
        $subscription = $this->getSubscription();
        $currentPlan = $subscription?->plan ?? TenantSubscription::PLAN_FREE;

        return (TenantSubscription::PLAN_HIERARCHY[$currentPlan] ?? 0)
            >= (TenantSubscription::PLAN_HIERARCHY[$requiredPlan] ?? 0);
    }

    /**
     * Get all licensed plugins
     */
    public function getLicensedPlugins(): array
    {
        $cacheKey = 'licensed_plugins';

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $plan = $this->getCurrentPlan();

            // Get plugins included in plan
            $includedPlugins = $this->getPluginsForPlan($plan);

            // Get additional licensed plugins
            $licensedPlugins = DB::table('plugin_licenses')->where('status', PluginLicense::STATUS_ACTIVE)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->pluck('plugin_slug')
                ->toArray();

            return array_unique(array_merge($includedPlugins, $licensedPlugins));
        });
    }

    /**
     * Get all enabled features
     */
    public function getEnabledFeatures(): array
    {
        $cacheKey = 'enabled_features';

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $licensedPlugins = $this->getLicensedPlugins();
            $currentPlan = $this->getCurrentPlan();

            return DB::table('feature_flags')->where(function ($query) use ($licensedPlugins, $currentPlan) {
                $query->where('is_enabled', true)
                    ->orWhereIn('plugin_slug', $licensedPlugins)
                    ->orWhere(function ($q) use ($currentPlan) {
                        $q->whereNotNull('plan_required')
                            ->whereIn('plan_required', $this->getPlansUpTo($currentPlan));
                    });
            })
                ->pluck('feature_key')
                ->toArray();
        });
    }

    /**
     * Check usage limits for a metric
     */
    public function checkUsageLimit(string $metric): array
    {
        $plan = $this->getCurrentPlan();
        $limit = self::PLAN_LIMITS[$plan][$metric] ?? null;

        $usage = DB::table('plugin_usages')->where('metric', $metric)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->first();

        $used = $usage?->quantity ?? 0;

        return [
            'allowed' => $limit === null || $used < $limit,
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit ? max(0, $limit - $used) : null,
            'percentage' => $limit ? min(100, round(($used / $limit) * 100, 1)) : null,
        ];
    }

    /**
     * Track usage for a metric
     */
    public function trackUsage(string $metric, int $amount = 1): void
    {
        $plan = $this->getCurrentPlan();
        $limit = self::PLAN_LIMITS[$plan][$metric] ?? null;

        PluginUsage::getOrCreateForPeriod('core', $metric, $limit)
            ->incrementUsage($amount);
    }

    /**
     * Get all usage stats
     */
    public function getUsageStats(): array
    {
        $plan = $this->getCurrentPlan();
        $limits = self::PLAN_LIMITS[$plan] ?? [];
        $stats = [];

        foreach ($limits as $metric => $limit) {
            $stats[$metric] = $this->checkUsageLimit($metric);
        }

        return $stats;
    }

    /**
     * Get license state for API response
     */
    public function getLicenseState(): array
    {
        $subscription = $this->getSubscription();

        return [
            'plan' => $subscription?->plan ?? TenantSubscription::PLAN_FREE,
            'status' => $subscription?->status ?? TenantSubscription::STATUS_ACTIVE,
            'billing_cycle' => $subscription?->billing_cycle ?? TenantSubscription::CYCLE_MONTHLY,
            'user_count' => $subscription?->user_count ?? 1,
            'plugins' => $this->getLicensedPlugins(),
            'features' => $this->getEnabledFeatures(),
            'usage' => $this->getUsageStats(),
            'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
            'current_period_end' => $subscription?->current_period_end?->toIso8601String(),
        ];
    }

    /**
     * Get available plugins for purchase
     */
    public function getAvailablePlugins(): Collection
    {
        $licensed = $this->getLicensedPlugins();

        return Plugin::active()
            ->orderBy('display_order')
            ->get()
            ->map(function ($plugin) use ($licensed) {
                $plugin->is_licensed = in_array($plugin->slug, $licensed);
                return $plugin;
            });
    }

    /**
     * Get available bundles
     */
    public function getAvailableBundles(): Collection
    {
        $licensed = $this->getLicensedPlugins();

        return PluginBundle::active()
            ->orderBy('display_order')
            ->get()
            ->map(function ($bundle) use ($licensed) {
                $bundlePlugins = $bundle->plugins ?? [];
                $licensedCount = count(array_intersect($bundlePlugins, $licensed));
                $bundle->licensed_count = $licensedCount;
                $bundle->is_fully_licensed = $licensedCount === count($bundlePlugins);
                return $bundle;
            });
    }

    /**
     * Clear all license caches
     */
    public function clearCache(): void
    {
        Cache::forget('tenant_subscription');
        Cache::forget('licensed_plugins');
        Cache::forget('enabled_features');

        // Clear plugin-specific caches
        $plugins = Plugin::pluck('slug');
        foreach ($plugins as $slug) {
            Cache::forget("plugin_license:{$slug}");
        }

        // Clear feature caches
        $features = FeatureFlag::pluck('feature_key');
        foreach ($features as $key) {
            Cache::forget("feature_flag:{$key}");
        }
    }

    /**
     * Check if plugin is included in current plan
     */
    private function isIncludedInPlan(string $pluginSlug): bool
    {
        $plan = $this->getCurrentPlan();
        $includedPlugins = $this->getPluginsForPlan($plan);

        return in_array($pluginSlug, $includedPlugins);
    }

    /**
     * Get all plugins included in a plan (cumulative)
     */
    private function getPluginsForPlan(string $plan): array
    {
        $plugins = [];
        $planOrder = array_keys(TenantSubscription::PLAN_HIERARCHY);

        foreach ($planOrder as $planName) {
            $plugins = array_merge($plugins, self::PLAN_PLUGINS[$planName] ?? []);
            if ($planName === $plan) {
                break;
            }
        }

        return $plugins;
    }

    /**
     * Get all plans up to and including the given plan
     */
    private function getPlansUpTo(string $plan): array
    {
        $plans = [];
        $planOrder = array_keys(TenantSubscription::PLAN_HIERARCHY);

        foreach ($planOrder as $planName) {
            $plans[] = $planName;
            if ($planName === $plan) {
                break;
            }
        }

        return $plans;
    }
}
