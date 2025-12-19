<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Modules\Repositories\EloquentBlockRepository;
use App\Domain\Modules\Repositories\EloquentFieldRepository;
use App\Domain\Modules\Repositories\EloquentModuleRepository;
use App\Domain\Modules\Repositories\Interfaces\BlockRepositoryInterface;
use App\Domain\Modules\Repositories\Interfaces\FieldRepositoryInterface;
use App\Domain\Modules\Repositories\Interfaces\ModuleRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind repository interfaces to implementations
        $this->app->bind(ModuleRepositoryInterface::class, EloquentModuleRepository::class);
        $this->app->bind(FieldRepositoryInterface::class, EloquentFieldRepository::class);
        $this->app->bind(BlockRepositoryInterface::class, EloquentBlockRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
