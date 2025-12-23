<?php

declare(strict_types=1);

namespace App\Infrastructure\Plugin;

use App\Domain\Plugin\Contracts\PluginServiceProviderInterface;
use App\Domain\Plugin\Repositories\PluginRepositoryInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;

class PluginLoaderService
{
    private array $loadedPlugins = [];
    private array $registeredProviders = [];

    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * Discover and load all active plugins.
     */
    public function loadActivePlugins(): void
    {
        $pluginsPath = base_path('plugins');

        if (!is_dir($pluginsPath)) {
            return;
        }

        $plugins = scandir($pluginsPath);

        foreach ($plugins as $plugin) {
            if ($plugin === '.' || $plugin === '..') {
                continue;
            }

            $pluginPath = $pluginsPath . '/' . $plugin;

            if (!is_dir($pluginPath)) {
                continue;
            }

            try {
                $this->loadPlugin($plugin);
            } catch (\Exception $e) {
                Log::warning("Failed to load plugin: {$plugin}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Load a specific plugin by slug.
     */
    public function loadPlugin(string $slug): bool
    {
        if (isset($this->loadedPlugins[$slug])) {
            return true; // Already loaded
        }

        $pluginPath = base_path("plugins/{$slug}");
        $manifestPath = "{$pluginPath}/manifest.json";

        if (!file_exists($manifestPath)) {
            throw new \RuntimeException("Plugin manifest not found: {$slug}");
        }

        $manifest = PluginManifest::fromJson($manifestPath);

        // Check dependencies
        foreach ($manifest->getDependencies() as $dependency) {
            if (!isset($this->loadedPlugins[$dependency])) {
                if (!$this->loadPlugin($dependency)) {
                    throw new \RuntimeException("Plugin dependency not available: {$dependency}");
                }
            }
        }

        // Load autoloader if exists
        $autoloadPath = "{$pluginPath}/vendor/autoload.php";
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
        }

        // Register service provider
        $providerClass = $manifest->getServiceProvider();

        if (!class_exists($providerClass)) {
            throw new \RuntimeException("Plugin service provider not found: {$providerClass}");
        }

        $provider = $this->app->register($providerClass);

        $this->loadedPlugins[$slug] = [
            'manifest' => $manifest,
            'provider' => $provider,
        ];

        $this->registeredProviders[$slug] = $provider;

        Log::info("Plugin loaded: {$slug}");

        return true;
    }

    /**
     * Unload a plugin.
     */
    public function unloadPlugin(string $slug): bool
    {
        if (!isset($this->loadedPlugins[$slug])) {
            return false;
        }

        $provider = $this->registeredProviders[$slug] ?? null;

        if ($provider instanceof PluginServiceProviderInterface) {
            $provider->onDeactivate();
        }

        unset($this->loadedPlugins[$slug]);
        unset($this->registeredProviders[$slug]);

        return true;
    }

    /**
     * Get loaded plugins.
     */
    public function getLoadedPlugins(): array
    {
        return $this->loadedPlugins;
    }

    /**
     * Check if a plugin is loaded.
     */
    public function isPluginLoaded(string $slug): bool
    {
        return isset($this->loadedPlugins[$slug]);
    }

    /**
     * Get a plugin's manifest.
     */
    public function getPluginManifest(string $slug): ?PluginManifest
    {
        return $this->loadedPlugins[$slug]['manifest'] ?? null;
    }

    /**
     * Get a plugin's service provider.
     */
    public function getPluginProvider(string $slug): ?PluginServiceProviderInterface
    {
        return $this->registeredProviders[$slug] ?? null;
    }

    /**
     * Discover available plugins (not necessarily loaded).
     */
    public function discoverPlugins(): array
    {
        $plugins = [];
        $pluginsPath = base_path('plugins');

        if (!is_dir($pluginsPath)) {
            return $plugins;
        }

        $dirs = scandir($pluginsPath);

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $manifestPath = "{$pluginsPath}/{$dir}/manifest.json";

            if (file_exists($manifestPath)) {
                try {
                    $manifest = PluginManifest::fromJson($manifestPath);
                    $plugins[$dir] = [
                        'manifest' => $manifest->toArray(),
                        'loaded' => isset($this->loadedPlugins[$dir]),
                    ];
                } catch (\Exception $e) {
                    Log::warning("Invalid plugin manifest: {$dir}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $plugins;
    }

    /**
     * Activate a plugin (install and load).
     */
    public function activatePlugin(string $slug): bool
    {
        $this->loadPlugin($slug);

        $provider = $this->registeredProviders[$slug] ?? null;

        if ($provider instanceof PluginServiceProviderInterface) {
            $provider->onActivate();
        }

        return true;
    }

    /**
     * Deactivate a plugin.
     */
    public function deactivatePlugin(string $slug): bool
    {
        return $this->unloadPlugin($slug);
    }

    /**
     * Uninstall a plugin.
     */
    public function uninstallPlugin(string $slug, string $dataAction = 'keep'): bool
    {
        $provider = $this->registeredProviders[$slug] ?? null;

        if ($provider instanceof PluginServiceProviderInterface) {
            $provider->onUninstall($dataAction);
        }

        return $this->unloadPlugin($slug);
    }
}
