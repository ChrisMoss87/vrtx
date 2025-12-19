<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Services;

use App\Domain\Workflow\ValueObjects\ActionType;

/**
 * Domain service interface for dispatching workflow actions.
 *
 * This service acts as a facade to action handlers in the infrastructure layer.
 * It receives action type and configuration, then delegates to the appropriate handler.
 */
class ActionDispatcherService
{
    /**
     * Registered action handlers.
     *
     * @var array<string, callable>
     */
    private array $handlers = [];

    /**
     * Register an action handler.
     *
     * @param string|ActionType $actionType
     * @param callable $handler Handler receives (array $config, array $context) and returns array
     */
    public function registerHandler(string|ActionType $actionType, callable $handler): void
    {
        $key = $actionType instanceof ActionType ? $actionType->value : $actionType;
        $this->handlers[$key] = $handler;
    }

    /**
     * Dispatch an action to its handler.
     *
     * @param ActionType $actionType The type of action to execute
     * @param array<string, mixed> $config Action configuration
     * @param array<string, mixed> $context Execution context (record data, etc.)
     * @return array<string, mixed> Result of the action
     * @throws \RuntimeException If no handler is registered for the action type
     */
    public function dispatch(ActionType $actionType, array $config, array $context): array
    {
        $handler = $this->handlers[$actionType->value] ?? null;

        if ($handler === null) {
            throw new \RuntimeException("No handler registered for action type: {$actionType->value}");
        }

        $result = $handler($config, $context);

        return is_array($result) ? $result : ['result' => $result];
    }

    /**
     * Dispatch an action by string name.
     *
     * @param string $actionType The action type name
     * @param array<string, mixed> $config Action configuration
     * @param array<string, mixed> $context Execution context
     * @return array<string, mixed> Result of the action
     * @throws \RuntimeException If no handler is registered for the action type
     */
    public function dispatchByName(string $actionType, array $config, array $context): array
    {
        $handler = $this->handlers[$actionType] ?? null;

        if ($handler === null) {
            throw new \RuntimeException("No handler registered for action type: {$actionType}");
        }

        $result = $handler($config, $context);

        return is_array($result) ? $result : ['result' => $result];
    }

    /**
     * Check if a handler is registered for an action type.
     */
    public function hasHandler(ActionType $actionType): bool
    {
        return isset($this->handlers[$actionType->value]);
    }

    /**
     * Check if a handler is registered for an action type by name.
     */
    public function hasHandlerByName(string $actionType): bool
    {
        return isset($this->handlers[$actionType]);
    }

    /**
     * Get all registered action types.
     *
     * @return array<string>
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->handlers);
    }
}
