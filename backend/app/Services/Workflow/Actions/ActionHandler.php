<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Handles dispatching workflow actions to their implementations.
 */
class ActionHandler
{
    protected array $handlers = [];

    public function __construct()
    {
        // Register default action handlers
        $this->registerDefaults();
    }

    /**
     * Register default action handlers.
     */
    protected function registerDefaults(): void
    {
        // Action type constants from workflow_steps table
        $this->register('send_email', SendEmailAction::class);
        $this->register('create_record', CreateRecordAction::class);
        $this->register('update_record', UpdateRecordAction::class);
        $this->register('update_field', UpdateFieldAction::class);
        $this->register('delete_record', DeleteRecordAction::class);
        $this->register('webhook', WebhookAction::class);
        $this->register('assign_user', AssignUserAction::class);
        $this->register('send_notification', SendNotificationAction::class);
        $this->register('delay', DelayAction::class);
        $this->register('move_stage', MoveStageAction::class);
        $this->register('add_tag', AddTagAction::class);
        $this->register('remove_tag', RemoveTagAction::class);
        $this->register('create_task', CreateTaskAction::class);
        $this->register('condition', ConditionBranchAction::class);
        $this->register('update_related', UpdateRelatedRecordAction::class);
    }

    /**
     * Register an action handler.
     */
    public function register(string $actionType, string $handlerClass): void
    {
        $this->handlers[$actionType] = $handlerClass;
    }

    /**
     * Handle an action.
     */
    public function handle(string $actionType, array $config, array $context): array
    {
        $handlerClass = $this->handlers[$actionType] ?? null;

        if (!$handlerClass) {
            throw new \InvalidArgumentException("Unknown action type: {$actionType}");
        }

        /** @var ActionInterface $handler */
        $handler = app($handlerClass);

        return $handler->execute($config, $context);
    }

    /**
     * Get all registered action types.
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * Get the configuration schema for an action type.
     */
    public function getConfigSchema(string $actionType): array
    {
        $handlerClass = $this->handlers[$actionType] ?? null;

        if (!$handlerClass) {
            throw new \InvalidArgumentException("Unknown action type: {$actionType}");
        }

        return $handlerClass::getConfigSchema();
    }

    /**
     * Get configuration schemas for all action types.
     */
    public function getAllConfigSchemas(): array
    {
        $schemas = [];

        foreach ($this->handlers as $actionType => $handlerClass) {
            $schemas[$actionType] = $handlerClass::getConfigSchema();
        }

        return $schemas;
    }

    /**
     * Validate action configuration.
     */
    public function validateConfig(string $actionType, array $config): array
    {
        $handlerClass = $this->handlers[$actionType] ?? null;

        if (!$handlerClass) {
            return ['action_type' => "Unknown action type: {$actionType}"];
        }

        /** @var ActionInterface $handler */
        $handler = app($handlerClass);

        return $handler->validate($config);
    }
}
