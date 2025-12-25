<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Modules\Repositories\Interfaces\BlockRepositoryInterface;
use App\Domain\Modules\Repositories\Interfaces\FieldRepositoryInterface;
use App\Domain\Modules\Repositories\Interfaces\ModuleRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\DbBlockRepository;
use App\Infrastructure\Persistence\Database\Repositories\DbFieldRepository;
use App\Infrastructure\Persistence\Database\Repositories\DbModuleRepository;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind repository interfaces to implementations
        $this->app->bind(ModuleRepositoryInterface::class, DbModuleRepository::class);
        $this->app->bind(FieldRepositoryInterface::class, DbFieldRepository::class);
        $this->app->bind(BlockRepositoryInterface::class, DbBlockRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
