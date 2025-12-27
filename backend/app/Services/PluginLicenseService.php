<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Plugin\Repositories\PluginRepositoryInterface;
use App\Domain\Plugin\Services\PluginLicenseValidationService;
use App\Domain\Plugin\Services\PluginUsageService;
use App\Domain\Plugin\ValueObjects\LicenseStatus;
use App\Domain\Plugin\ValueObjects\PluginSlug;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PluginLicenseService
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private PluginRepositoryInterface $pluginRepository,
        private PluginLicenseValidationService $licenseValidation,
        private PluginUsageService $usageService,
    ) {}

    /**
     * Get current subscription.
     */
    public function getSubscription(): ?TenantSubscription
    {
        return Cache::remember('tenant_subscription', self::CACHE_TTL, function () {
            $row = DB::table('tenant_subscriptions')->first();

            if (!$row) {
                return null;
            }

            $subscription = new TenantSubscription();
            $subscription->plan = $row->plan ?? TenantSubscription::PLAN_FREE;
            $subscription->status = $row->status ?? TenantSubscription::STATUS_ACTIVE;
            $subscription->billing_cycle = $row->billing_cycle ?? TenantSubscription::CYCLE_MONTHLY;
            $subscription->user_count = $row->user_count ?? 1;
            $subscription->trial_ends_at = $row->trial_ends_at ? new \DateTimeImmutable($row->trial_ends_at) : null;
            $subscription->current_period_end = $row->current_period_end ? new \DateTimeImmutable($row->current_period_end) : null;

            return $subscription;
        });
    }

    /**
     * Get current plan.
     */
    public function getCurrentPlan(): string
    {
        return $this->getSubscription()?->plan ?? TenantSubscription::PLAN_FREE;
    }

    /**
     * Check if a plugin is licensed for the current tenant.
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
            return $this->pluginRepository->isPluginInstalled($pluginSlug);
        });
    }

    /**
     * Check if a feature is enabled.
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
     * Check if current plan meets or exceeds required plan.
     */
    public function hasPlan(string $requiredPlan): bool
    {
        $subscription = $this->getSubscription();
        $currentPlan = $subscription?->plan ?? TenantSubscription::PLAN_FREE;

        return (TenantSubscription::PLAN_HIERARCHY[$currentPlan] ?? 0)
            >= (TenantSubscription::PLAN_HIERARCHY[$requiredPlan] ?? 0);
    }

    /**
     * Get all licensed plugins.
     */
    public function getLicensedPlugins(): array
    {
        $cacheKey = 'licensed_plugins';

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $plan = $this->getCurrentPlan();

            // Get plugins included in plan
            $includedPlugins = $this->licenseValidation->getPluginsForPlan($plan);

            // Get additional licensed plugins
            $licenses = $this->pluginRepository->getActiveLicenses();
            $licensedPlugins = array_column($licenses, 'plugin_slug');

            return array_unique(array_merge($includedPlugins, $licensedPlugins));
        });
    }

    /**
     * Get all enabled features.
     */
    public function getEnabledFeatures(): array
    {
        $cacheKey = 'enabled_features';

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $licensedPlugins = $this->getLicensedPlugins();
            $currentPlan = $this->getCurrentPlan();

            return DB::table('feature_flags')
                ->where(function ($query) use ($licensedPlugins, $currentPlan) {
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
     * Check usage limits for a metric.
     */
    public function checkUsageLimit(string $metric): array
    {
        $plan = $this->getCurrentPlan();
        $limit = $this->usageService->getMetricLimit($plan, $metric);

        $usage = $this->pluginRepository->getPluginUsage('core', $metric);
        $used = $usage['quantity'] ?? 0;

        return $this->usageService->calculateUsageMetrics($used, $limit);
    }

    /**
     * Track usage for a metric.
     */
    public function trackUsage(string $metric, int $amount = 1): void
    {
        $plan = $this->getCurrentPlan();
        $limit = $this->usageService->getMetricLimit($plan, $metric);

        $this->pluginRepository->trackUsage('core', $metric, $amount, $limit);
    }

    /**
     * Get all usage stats.
     */
    public function getUsageStats(): array
    {
        $plan = $this->getCurrentPlan();
        $limits = $this->usageService->getLimitsForPlan($plan);
        $stats = [];

        foreach (array_keys($limits) as $metric) {
            $stats[$metric] = $this->checkUsageLimit($metric);
        }

        return $stats;
    }

    /**
     * Get license state for API response.
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
            'trial_ends_at' => $subscription?->trial_ends_at?->format(\DateTimeInterface::ISO8601),
            'current_period_end' => $subscription?->current_period_end?->format(\DateTimeInterface::ISO8601),
        ];
    }

    /**
     * Get available plugins for purchase.
     */
    public function getAvailablePlugins(): array
    {
        $licensed = $this->getLicensedPlugins();
        $plugins = $this->pluginRepository->getAvailablePlugins();

        return array_map(function (array $plugin) use ($licensed) {
            $plugin['is_licensed'] = in_array($plugin['slug'], $licensed, true);
            return $plugin;
        }, $plugins);
    }

    /**
     * Get available bundles.
     */
    public function getAvailableBundles(): array
    {
        $licensed = $this->getLicensedPlugins();
        $bundles = $this->pluginRepository->getActiveBundles();

        return array_map(function (array $bundle) use ($licensed) {
            $bundlePlugins = $bundle['plugins'] ?? [];
            $licensedCount = count(array_intersect($bundlePlugins, $licensed));
            $bundle['licensed_count'] = $licensedCount;
            $bundle['is_fully_licensed'] = $licensedCount === count($bundlePlugins);
            return $bundle;
        }, $bundles);
    }

    /**
     * Clear all license caches.
     */
    public function clearCache(): void
    {
        Cache::forget('tenant_subscription');
        Cache::forget('licensed_plugins');
        Cache::forget('enabled_features');

        // Clear plugin-specific caches
        $plugins = $this->pluginRepository->findAll();
        foreach ($plugins as $plugin) {
            Cache::forget("plugin_license:{$plugin['slug']}");
        }

        // Clear feature caches
        $features = DB::table('feature_flags')->pluck('feature_key');
        foreach ($features as $key) {
            Cache::forget("feature_flag:{$key}");
        }
    }

    /**
     * Check if plugin is included in current plan.
     */
    private function isIncludedInPlan(string $pluginSlug): bool
    {
        $plan = $this->getCurrentPlan();

        return $this->licenseValidation->isPluginIncludedInPlan(
            PluginSlug::fromString($pluginSlug),
            $plan
        );
    }

    /**
     * Get all plans up to and including the given plan.
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
