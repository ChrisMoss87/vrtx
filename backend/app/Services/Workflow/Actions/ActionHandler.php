<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use App\Models\WorkflowStep;

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
        $this->register(WorkflowStep::ACTION_SEND_EMAIL, SendEmailAction::class);
        $this->register(WorkflowStep::ACTION_CREATE_RECORD, CreateRecordAction::class);
        $this->register(WorkflowStep::ACTION_UPDATE_RECORD, UpdateRecordAction::class);
        $this->register(WorkflowStep::ACTION_UPDATE_FIELD, UpdateFieldAction::class);
        $this->register(WorkflowStep::ACTION_DELETE_RECORD, DeleteRecordAction::class);
        $this->register(WorkflowStep::ACTION_WEBHOOK, WebhookAction::class);
        $this->register(WorkflowStep::ACTION_ASSIGN_USER, AssignUserAction::class);
        $this->register(WorkflowStep::ACTION_SEND_NOTIFICATION, SendNotificationAction::class);
        $this->register(WorkflowStep::ACTION_DELAY, DelayAction::class);
        $this->register(WorkflowStep::ACTION_MOVE_STAGE, MoveStageAction::class);
        $this->register(WorkflowStep::ACTION_ADD_TAG, AddTagAction::class);
        $this->register(WorkflowStep::ACTION_REMOVE_TAG, RemoveTagAction::class);
        $this->register(WorkflowStep::ACTION_CREATE_TASK, CreateTaskAction::class);
        $this->register(WorkflowStep::ACTION_CONDITION, ConditionBranchAction::class);
        $this->register(WorkflowStep::ACTION_UPDATE_RELATED, UpdateRelatedRecordAction::class);
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
