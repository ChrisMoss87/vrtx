<?php

declare(strict_types=1);

namespace App\Infrastructure\Plugin;

use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Plugin\Contracts\PluginManifestInterface;

class PluginManifest implements PluginManifestInterface
{
    public function __construct(
        private readonly string $slug,
        private readonly string $name,
        private readonly string $version,
        private readonly string $description,
        private readonly string $category,
        private readonly string $serviceProvider,
        private readonly array $dependencies = [],
        private readonly array $routes = [],
        private readonly array $migrations = [],
        private readonly array $permissions = [],
        private readonly ?string $communicationChannel = null,
        private readonly array $usageMetrics = [],
    ) {}

    public static function fromJson(string $jsonPath): self
    {
        if (!file_exists($jsonPath)) {
            throw new \InvalidArgumentException("Manifest not found: {$jsonPath}");
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Invalid manifest JSON: " . json_last_error_msg());
        }

        return self::fromArray($data);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            slug: $data['slug'],
            name: $data['name'],
            version: $data['version'] ?? '1.0.0',
            description: $data['description'] ?? '',
            category: $data['category'] ?? 'general',
            serviceProvider: $data['service_provider'],
            dependencies: $data['dependencies'] ?? [],
            routes: $data['routes'] ?? [],
            migrations: $data['migrations'] ?? [],
            permissions: $data['permissions'] ?? [],
            communicationChannel: $data['communication_channel'] ?? null,
            usageMetrics: $data['usage_metrics'] ?? [],
        );
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function getServiceProvider(): string
    {
        return $this->serviceProvider;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getMigrations(): array
    {
        return $this->migrations;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function getCommunicationChannel(): ?ChannelType
    {
        if (!$this->communicationChannel) {
            return null;
        }

        return ChannelType::tryFrom($this->communicationChannel);
    }

    public function getUsageMetrics(): array
    {
        return $this->usageMetrics;
    }

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'category' => $this->category,
            'service_provider' => $this->serviceProvider,
            'dependencies' => $this->dependencies,
            'routes' => $this->routes,
            'migrations' => $this->migrations,
            'permissions' => $this->permissions,
            'communication_channel' => $this->communicationChannel,
            'usage_metrics' => $this->usageMetrics,
        ];
    }
}
