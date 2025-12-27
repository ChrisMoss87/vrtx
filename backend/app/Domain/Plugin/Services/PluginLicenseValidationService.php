<?php

declare(strict_types=1);

namespace App\Domain\Plugin\Services;

use App\Domain\Plugin\Entities\Plugin;
use App\Domain\Plugin\Entities\PluginLicense;
use App\Domain\Plugin\ValueObjects\PluginSlug;
use App\Domain\Plugin\ValueObjects\PluginTier;

final class PluginLicenseValidationService
{
    /**
     * Validate if a license can be created for a plugin.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validateLicenseCreation(
        Plugin $plugin,
        ?PluginLicense $existingLicense,
        string $currentPlan,
    ): array {
        $errors = [];

        // Check if plugin is active in catalog
        if (!$plugin->isActive()) {
            $errors[] = 'Plugin is not available for licensing';
        }

        // Check if already has an active license
        if ($existingLicense !== null && $existingLicense->isValid()) {
            $errors[] = 'Plugin is already licensed';
        }

        // Check plan requirements
        if ($plugin->hasRequirements()) {
            $requirements = $plugin->getRequirements();
            if (isset($requirements['plan'])) {
                $requiredTier = PluginTier::fromString($requirements['plan']);
                $currentTier = $this->getPlanTier($currentPlan);

                if (!$currentTier->isAtLeast($requiredTier)) {
                    $errors[] = sprintf(
                        'This plugin requires a %s plan or higher',
                        $requiredTier->value()
                    );
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate if a license can be cancelled.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validateLicenseCancellation(PluginLicense $license): array
    {
        $errors = [];

        if (!$license->isValid()) {
            $errors[] = 'License is not active';
        }

        if ($license->isFromBundle()) {
            $errors[] = 'Cannot cancel a bundled license individually. Cancel the bundle instead.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate if a license can be reactivated.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validateLicenseReactivation(
        PluginLicense $license,
        Plugin $plugin,
    ): array {
        $errors = [];

        if ($license->isValid()) {
            $errors[] = 'License is already active';
        }

        if (!$license->getStatus()->canBeReactivated()) {
            $errors[] = 'License cannot be reactivated';
        }

        if (!$plugin->isActive()) {
            $errors[] = 'Plugin is no longer available';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if a plugin is included in a subscription plan.
     */
    public function isPluginIncludedInPlan(PluginSlug $pluginSlug, string $plan): bool
    {
        $includedPlugins = $this->getPluginsForPlan($plan);
        return in_array($pluginSlug->value(), $includedPlugins, true);
    }

    /**
     * Get all plugins included in a plan (cumulative).
     *
     * @return string[]
     */
    public function getPluginsForPlan(string $plan): array
    {
        $planPlugins = [
            'free' => [
                'core-modules',
                'core-datatable',
                'core-kanban',
                'core-dashboards',
                'core-workflows-basic',
            ],
            'starter' => [
                'core-reports',
                'core-email',
                'core-import-export',
            ],
            'professional' => [
                'forecasting-basic',
                'quotes-view',
                'web-forms-basic',
                'blueprints-basic',
            ],
            'business' => [
                'forecasting-pro',
                'quotes-invoices',
                'duplicate-detection',
                'deal-rotting',
                'web-forms-pro',
                'workflows-advanced',
                'blueprints-pro',
            ],
            'enterprise' => [
                'time-machine',
                'scenario-planner',
                'revenue-graph',
                'deal-rooms',
                'competitor-battlecards',
                'process-recorder',
                'api-unlimited',
            ],
        ];

        $planOrder = ['free', 'starter', 'professional', 'business', 'enterprise'];
        $plugins = [];

        foreach ($planOrder as $planName) {
            $plugins = array_merge($plugins, $planPlugins[$planName] ?? []);
            if ($planName === $plan) {
                break;
            }
        }

        return $plugins;
    }

    private function getPlanTier(string $plan): PluginTier
    {
        return match ($plan) {
            'free', 'starter' => PluginTier::core(),
            'professional' => PluginTier::professional(),
            'business' => PluginTier::advanced(),
            'enterprise' => PluginTier::enterprise(),
            default => PluginTier::core(),
        };
    }
}
