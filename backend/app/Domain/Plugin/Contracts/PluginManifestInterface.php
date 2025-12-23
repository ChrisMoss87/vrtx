<?php

declare(strict_types=1);

namespace App\Domain\Plugin\Contracts;

use App\Domain\Communication\ValueObjects\ChannelType;

interface PluginManifestInterface
{
    /**
     * Get the unique plugin slug.
     */
    public function getSlug(): string;

    /**
     * Get the plugin display name.
     */
    public function getName(): string;

    /**
     * Get the plugin version.
     */
    public function getVersion(): string;

    /**
     * Get the plugin description.
     */
    public function getDescription(): string;

    /**
     * Get the plugin category.
     */
    public function getCategory(): string;

    /**
     * Get plugin dependencies (other plugin slugs).
     */
    public function getDependencies(): array;

    /**
     * Get the service provider class name.
     */
    public function getServiceProvider(): string;

    /**
     * Get route configuration.
     */
    public function getRoutes(): array;

    /**
     * Get migration paths.
     */
    public function getMigrations(): array;

    /**
     * Get required permissions.
     */
    public function getPermissions(): array;

    /**
     * Get the communication channel type if this plugin provides one.
     */
    public function getCommunicationChannel(): ?ChannelType;

    /**
     * Get usage metrics configuration.
     */
    public function getUsageMetrics(): array;

    /**
     * Convert to array for storage/serialization.
     */
    public function toArray(): array;
}
