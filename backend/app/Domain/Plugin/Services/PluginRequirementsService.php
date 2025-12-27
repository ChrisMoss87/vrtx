<?php

declare(strict_types=1);

namespace App\Domain\Plugin\Services;

use App\Domain\Plugin\Entities\Plugin;
use App\Domain\Plugin\ValueObjects\PluginSlug;

final class PluginRequirementsService
{
    /**
     * Check if a plugin's requirements are met.
     *
     * @param Plugin $plugin The plugin to check
     * @param string[] $licensedPlugins Array of licensed plugin slugs
     * @param string $currentPlan Current subscription plan
     * @return array{met: bool, missing: array}
     */
    public function checkRequirements(
        Plugin $plugin,
        array $licensedPlugins,
        string $currentPlan,
    ): array {
        $requirements = $plugin->getRequirements();
        $missing = [];

        // Check required plugins
        if (!empty($requirements['plugins'])) {
            foreach ($requirements['plugins'] as $requiredPlugin) {
                if (!in_array($requiredPlugin, $licensedPlugins, true)) {
                    $missing['plugins'][] = $requiredPlugin;
                }
            }
        }

        // Check required plan
        if (!empty($requirements['plan'])) {
            $requiredPlan = $requirements['plan'];
            if (!$this->isPlanSufficient($currentPlan, $requiredPlan)) {
                $missing['plan'] = $requiredPlan;
            }
        }

        // Check required features
        if (!empty($requirements['features'])) {
            foreach ($requirements['features'] as $requiredFeature) {
                // Features are typically tied to plugins or plans
                // This would integrate with a feature flag system
                $missing['features'][] = $requiredFeature;
            }
        }

        return [
            'met' => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Get all plugins that depend on a given plugin.
     *
     * @param PluginSlug $pluginSlug The plugin to check dependents for
     * @param Plugin[] $allPlugins All available plugins
     * @return Plugin[]
     */
    public function getDependentPlugins(PluginSlug $pluginSlug, array $allPlugins): array
    {
        $dependents = [];

        foreach ($allPlugins as $plugin) {
            $requirements = $plugin->getRequirements();
            $requiredPlugins = $requirements['plugins'] ?? [];

            if (in_array($pluginSlug->value(), $requiredPlugins, true)) {
                $dependents[] = $plugin;
            }
        }

        return $dependents;
    }

    /**
     * Resolve plugin dependencies and return installation order.
     *
     * @param PluginSlug[] $pluginsToInstall Plugins to install
     * @param Plugin[] $pluginCatalog All available plugins indexed by slug
     * @return array{order: PluginSlug[], missing: string[]}
     */
    public function resolveDependencyOrder(array $pluginsToInstall, array $pluginCatalog): array
    {
        $order = [];
        $visited = [];
        $missing = [];

        foreach ($pluginsToInstall as $pluginSlug) {
            $this->visitPlugin(
                $pluginSlug,
                $pluginCatalog,
                $visited,
                $order,
                $missing,
                []
            );
        }

        return [
            'order' => $order,
            'missing' => $missing,
        ];
    }

    private function visitPlugin(
        PluginSlug $pluginSlug,
        array $pluginCatalog,
        array &$visited,
        array &$order,
        array &$missing,
        array $stack,
    ): void {
        $slug = $pluginSlug->value();

        if (in_array($slug, $visited, true)) {
            return;
        }

        // Detect circular dependencies
        if (in_array($slug, $stack, true)) {
            return;
        }

        if (!isset($pluginCatalog[$slug])) {
            $missing[] = $slug;
            return;
        }

        $stack[] = $slug;
        $plugin = $pluginCatalog[$slug];
        $requirements = $plugin->getRequirements();

        // Visit required plugins first
        foreach ($requirements['plugins'] ?? [] as $requiredSlug) {
            $this->visitPlugin(
                PluginSlug::fromString($requiredSlug),
                $pluginCatalog,
                $visited,
                $order,
                $missing,
                $stack
            );
        }

        $visited[] = $slug;
        $order[] = $pluginSlug;
    }

    private function isPlanSufficient(string $currentPlan, string $requiredPlan): bool
    {
        $planHierarchy = [
            'free' => 0,
            'starter' => 1,
            'professional' => 2,
            'business' => 3,
            'enterprise' => 4,
        ];

        $currentLevel = $planHierarchy[$currentPlan] ?? 0;
        $requiredLevel = $planHierarchy[$requiredPlan] ?? 0;

        return $currentLevel >= $requiredLevel;
    }
}
