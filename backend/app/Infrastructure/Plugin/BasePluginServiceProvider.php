<?php

declare(strict_types=1);

namespace App\Infrastructure\Plugin;

use App\Domain\Communication\Services\CommunicationAggregatorService;
use App\Domain\Communication\Contracts\CommunicationChannelInterface;
use App\Domain\Plugin\Contracts\PluginManifestInterface;
use App\Domain\Plugin\Contracts\PluginServiceProviderInterface;
use App\Domain\Plugin\Repositories\PluginRepositoryInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

abstract class BasePluginServiceProvider extends ServiceProvider implements PluginServiceProviderInterface
{
    protected ?PluginManifestInterface $manifest = null;

    /**
     * Get the plugin manifest. Must be implemented by each plugin.
     */
    abstract public function getManifest(): PluginManifestInterface;

    /**
     * Get the communication channel adapter if this plugin provides one.
     */
    protected function getChannelAdapter(): ?CommunicationChannelInterface
    {
        return null;
    }

    /**
     * Register plugin-specific services.
     */
    protected function registerPluginServices(): void
    {
        // Override in plugin to register services
    }

    /**
     * Boot plugin-specific services.
     */
    protected function bootPluginServices(): void
    {
        // Override in plugin to boot services
    }

    /**
     * Register the plugin.
     */
    public function register(): void
    {
        if (!$this->isLicensed()) {
            return;
        }

        $this->registerPluginServices();
    }

    /**
     * Boot the plugin.
     */
    public function boot(): void
    {
        if (!$this->isLicensed()) {
            return;
        }

        $this->loadRoutes();
        $this->loadMigrations();
        $this->registerCommunicationChannel();
        $this->bootPluginServices();
    }

    /**
     * Check if the plugin is licensed.
     */
    public function isLicensed(): bool
    {
        // In development, always return true
        if (app()->environment('local', 'testing')) {
            return true;
        }

        try {
            $repository = app(PluginRepositoryInterface::class);
            return $repository->isPluginInstalled($this->getManifest()->getSlug());
        } catch (\Exception $e) {
            // If we can't check, default to not licensed
            return false;
        }
    }

    /**
     * Called when plugin is activated.
     */
    public function onActivate(): void
    {
        // Run migrations
        $this->runMigrations();
    }

    /**
     * Called when plugin is deactivated.
     */
    public function onDeactivate(): void
    {
        // Override if needed
    }

    /**
     * Called when plugin is uninstalled.
     */
    public function onUninstall(string $dataAction = 'keep'): void
    {
        match ($dataAction) {
            'keep' => $this->markDataAsOrphaned(),
            'archive' => $this->archiveData(),
            'delete' => $this->deleteData(),
            default => $this->markDataAsOrphaned(),
        };
    }

    /**
     * Get the route prefix.
     */
    public function getRoutePrefix(): string
    {
        return $this->getManifest()->getRoutes()['prefix'] ?? $this->getManifest()->getSlug();
    }

    /**
     * Get route middleware.
     */
    public function getRouteMiddleware(): array
    {
        return [
            'api',
            'auth:sanctum',
            'tenant',
            'plugin-license:' . $this->getManifest()->getSlug(),
        ];
    }

    /**
     * Load plugin routes.
     */
    protected function loadRoutes(): void
    {
        $routes = $this->getManifest()->getRoutes();

        if (empty($routes)) {
            return;
        }

        $routeFile = $routes['file'] ?? null;
        if (!$routeFile) {
            return;
        }

        $pluginPath = $this->getPluginPath();
        $fullPath = $pluginPath . '/' . $routeFile;

        if (!file_exists($fullPath)) {
            return;
        }

        Route::middleware($this->getRouteMiddleware())
            ->prefix('api/v1/' . $this->getRoutePrefix())
            ->group($fullPath);
    }

    /**
     * Load plugin migrations.
     */
    protected function loadMigrations(): void
    {
        $migrations = $this->getManifest()->getMigrations();

        if (empty($migrations)) {
            return;
        }

        $pluginPath = $this->getPluginPath();

        foreach ($migrations as $migration) {
            $fullPath = $pluginPath . '/' . dirname($migration);
            if (is_dir($fullPath)) {
                $this->loadMigrationsFrom($fullPath);
            }
        }
    }

    /**
     * Run plugin migrations.
     */
    protected function runMigrations(): void
    {
        $migrations = $this->getManifest()->getMigrations();

        if (empty($migrations)) {
            return;
        }

        $pluginPath = $this->getPluginPath();

        foreach ($migrations as $migration) {
            $fullPath = $pluginPath . '/' . dirname($migration);
            if (is_dir($fullPath)) {
                \Artisan::call('migrate', [
                    '--path' => $fullPath,
                    '--force' => true,
                ]);
            }
        }
    }

    /**
     * Register communication channel if plugin provides one.
     */
    protected function registerCommunicationChannel(): void
    {
        $channelType = $this->getManifest()->getCommunicationChannel();

        if (!$channelType) {
            return;
        }

        $adapter = $this->getChannelAdapter();

        if (!$adapter) {
            return;
        }

        try {
            $aggregator = app(CommunicationAggregatorService::class);
            $aggregator->registerChannel($adapter);
        } catch (\Exception $e) {
            // Communication service might not be available
        }
    }

    /**
     * Get the plugin directory path.
     */
    protected function getPluginPath(): string
    {
        return base_path('plugins/' . $this->getManifest()->getSlug());
    }

    /**
     * Mark plugin data as orphaned (keep but flag).
     */
    protected function markDataAsOrphaned(): void
    {
        // Override in plugin to mark data
    }

    /**
     * Archive plugin data.
     */
    protected function archiveData(): void
    {
        // Override in plugin to archive data
    }

    /**
     * Delete plugin data.
     */
    protected function deleteData(): void
    {
        // Override in plugin to delete data
    }
}
