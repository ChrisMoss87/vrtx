<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy;

use App\Domain\Tenancy\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Tenancy\DbTenantRepository;
use App\Infrastructure\Tenancy\Bootstrappers\CacheBootstrapper;
use App\Infrastructure\Tenancy\Bootstrappers\DatabaseBootstrapper;
use App\Infrastructure\Tenancy\Bootstrappers\FilesystemBootstrapper;
use App\Infrastructure\Tenancy\Bootstrappers\QueueBootstrapper;
use App\Infrastructure\Tenancy\Middleware\InitializeTenancyByDomain;
use App\Infrastructure\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use App\Infrastructure\Tenancy\Middleware\InitializeTenancyBySubdomain;
use App\Infrastructure\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public static string $controllerNamespace = '';

    public function register(): void
    {
        // Register the tenant repository
        $this->app->singleton(TenantRepositoryInterface::class, DbTenantRepository::class);

        // Register the tenancy manager
        $this->app->singleton(TenancyManager::class, function ($app) {
            $manager = new TenancyManager(
                $app->make(TenantRepositoryInterface::class)
            );

            // Register bootstrappers from config
            $bootstrappers = config('tenancy.bootstrappers', [
                DatabaseBootstrapper::class,
                CacheBootstrapper::class,
                FilesystemBootstrapper::class,
                QueueBootstrapper::class,
            ]);

            foreach ($bootstrappers as $bootstrapperClass) {
                $manager->addBootstrapper($app->make($bootstrapperClass));
            }

            return $manager;
        });

        // Register the tenant manager (for creating/deleting tenants)
        $this->app->singleton(TenantManager::class, function ($app) {
            return new TenantManager(
                $app->make(TenantRepositoryInterface::class),
                $app->make(TenancyManager::class),
            );
        });

        // Register helper file
        require_once __DIR__ . '/helpers.php';
    }

    public function boot(): void
    {
        $this->registerQueueListeners();
        $this->mapRoutes();
        $this->makeTenancyMiddlewareHighestPriority();
    }

    protected function registerQueueListeners(): void
    {
        QueueBootstrapper::registerListeners();
    }

    protected function mapRoutes(): void
    {
        $this->app->booted(function () {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant.php'));
            }

            if (file_exists(base_path('routes/tenant-api.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant-api.php'));
            }
        });
    }

    protected function makeTenancyMiddlewareHighestPriority(): void
    {
        $tenancyMiddleware = [
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByDomain::class,
            InitializeTenancyBySubdomain::class,
            InitializeTenancyByDomainOrSubdomain::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }
}
