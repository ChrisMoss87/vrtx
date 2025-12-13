<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Modules\Repositories\BlockRepositoryInterface;
use App\Domain\Modules\Repositories\FieldRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Workflow\Repositories\WorkflowExecutionRepositoryInterface;
use App\Domain\Workflow\Repositories\WorkflowRepositoryInterface;
use App\Domain\Workflow\Services\ActionDispatcherService;
use App\Domain\Workflow\Services\ConditionEvaluationService;
use App\Domain\Workflow\Services\WorkflowExecutionService;
use App\Domain\Workflow\Services\WorkflowTriggerEvaluatorService;
use App\Domain\Workflow\Services\WorkflowValidationService;
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

        // Register Workflow domain services as singletons
        $this->app->singleton(ConditionEvaluationService::class);
        $this->app->singleton(WorkflowValidationService::class);
        $this->app->singleton(WorkflowTriggerEvaluatorService::class);
        $this->app->singleton(ActionDispatcherService::class);

        // Register WorkflowExecutionService with its dependencies
        $this->app->singleton(WorkflowExecutionService::class, function ($app) {
            return new WorkflowExecutionService(
                $app->make(WorkflowRepositoryInterface::class),
                $app->make(WorkflowExecutionRepositoryInterface::class),
                $app->make(ConditionEvaluationService::class),
                $app->make(ActionDispatcherService::class),
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register action handlers
        $this->registerActionHandlers();
    }

    /**
     * Register workflow action handlers with the dispatcher.
     */
    private function registerActionHandlers(): void
    {
        $dispatcher = $this->app->make(ActionDispatcherService::class);

        // Register the legacy action handler as a bridge
        // This allows the new DDD workflow execution to use existing action handlers
        $dispatcher->registerHandler('send_email', function (array $config, array $context) {
            return $this->handleLegacyAction('send_email', $config, $context);
        });

        $dispatcher->registerHandler('send_notification', function (array $config, array $context) {
            return $this->handleLegacyAction('send_notification', $config, $context);
        });

        $dispatcher->registerHandler('update_field', function (array $config, array $context) {
            return $this->handleLegacyAction('update_field', $config, $context);
        });

        $dispatcher->registerHandler('create_record', function (array $config, array $context) {
            return $this->handleLegacyAction('create_record', $config, $context);
        });

        $dispatcher->registerHandler('create_task', function (array $config, array $context) {
            return $this->handleLegacyAction('create_task', $config, $context);
        });

        $dispatcher->registerHandler('assign_user', function (array $config, array $context) {
            return $this->handleLegacyAction('assign_user', $config, $context);
        });

        $dispatcher->registerHandler('move_stage', function (array $config, array $context) {
            return $this->handleLegacyAction('move_stage', $config, $context);
        });

        $dispatcher->registerHandler('webhook', function (array $config, array $context) {
            return $this->handleLegacyAction('webhook', $config, $context);
        });

        $dispatcher->registerHandler('delay', function (array $config, array $context) {
            // Delay action - just return success, actual delay is handled by job scheduling
            return ['delayed' => true, 'duration' => $config['duration'] ?? 0];
        });

        $dispatcher->registerHandler('condition', function (array $config, array $context) {
            // Condition branch - evaluate and return which branch to take
            $conditionService = $this->app->make(ConditionEvaluationService::class);
            $conditions = $config['conditions'] ?? [];
            $result = $conditionService->evaluate($conditions, $context);
            return ['condition_met' => $result, 'branch' => $result ? 'true' : 'false'];
        });

        $dispatcher->registerHandler('add_tag', function (array $config, array $context) {
            return $this->handleLegacyAction('add_tag', $config, $context);
        });

        $dispatcher->registerHandler('remove_tag', function (array $config, array $context) {
            return $this->handleLegacyAction('remove_tag', $config, $context);
        });

        $dispatcher->registerHandler('update_related_record', function (array $config, array $context) {
            return $this->handleLegacyAction('update_related_record', $config, $context);
        });
    }

    /**
     * Bridge to legacy action handlers.
     */
    private function handleLegacyAction(string $actionType, array $config, array $context): array
    {
        try {
            $legacyHandler = $this->app->make(\App\Services\Workflow\Actions\ActionHandler::class);
            $result = $legacyHandler->handle($actionType, $config, $context);
            return is_array($result) ? $result : ['result' => $result];
        } catch (\Exception $e) {
            throw new \RuntimeException("Action '{$actionType}' failed: {$e->getMessage()}", 0, $e);
        }
    }
}
