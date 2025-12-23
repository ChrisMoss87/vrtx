<?php

declare(strict_types=1);

namespace App\Domain\Plugin\Contracts;

interface PluginServiceProviderInterface
{
    /**
     * Get the plugin manifest.
     */
    public function getManifest(): PluginManifestInterface;

    /**
     * Register plugin services, bindings, etc.
     */
    public function register(): void;

    /**
     * Boot the plugin (after all services are registered).
     */
    public function boot(): void;

    /**
     * Called when the plugin is activated/installed.
     */
    public function onActivate(): void;

    /**
     * Called when the plugin is deactivated.
     */
    public function onDeactivate(): void;

    /**
     * Called when the plugin is uninstalled.
     * Handle data cleanup/archival here.
     */
    public function onUninstall(string $dataAction = 'keep'): void;

    /**
     * Check if the plugin is licensed for the current tenant.
     */
    public function isLicensed(): bool;

    /**
     * Get the route prefix for this plugin.
     */
    public function getRoutePrefix(): string;

    /**
     * Get middleware to apply to plugin routes.
     */
    public function getRouteMiddleware(): array;
}
