<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Modules\Repositories\BlockRepositoryInterface;
use App\Domain\Modules\Repositories\FieldRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Workflow\Repositories\WorkflowExecutionRepositoryInterface;
use App\Domain\Workflow\Repositories\WorkflowRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentBlockRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentFieldRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentModuleRecordRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentModuleRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Workflow\EloquentWorkflowExecutionRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Workflow\EloquentWorkflowRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Module repository interfaces to Eloquent implementations
        $this->app->bind(ModuleRepositoryInterface::class, EloquentModuleRepository::class);
        $this->app->bind(BlockRepositoryInterface::class, EloquentBlockRepository::class);
        $this->app->bind(FieldRepositoryInterface::class, EloquentFieldRepository::class);
        $this->app->bind(ModuleRecordRepositoryInterface::class, EloquentModuleRecordRepository::class);

        // Bind Workflow repository interfaces to Eloquent implementations
        $this->app->bind(WorkflowRepositoryInterface::class, EloquentWorkflowRepository::class);
        $this->app->bind(WorkflowExecutionRepositoryInterface::class, EloquentWorkflowExecutionRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
