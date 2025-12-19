<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Entities;

use App\Domain\Shared\Contracts\Entity;
use App\Domain\Workflow\ValueObjects\ActionConfig;
use App\Domain\Workflow\ValueObjects\ActionType;
use App\Domain\Workflow\ValueObjects\StepConditions;

/**
 * WorkflowStep entity.
 *
 * Represents a single step in a workflow that performs an action.
 */
final class WorkflowStep implements Entity
{
    private function __construct(
        private ?int $id,
        private ?int $workflowId,
        private int $order,
        private ?string $name,
        private ActionType $actionType,
        private ActionConfig $actionConfig,
        private StepConditions $conditions,
        private ?string $branchId,
        private bool $isParallel,
        private bool $continueOnError,
        private int $retryCount,
        private int $retryDelaySeconds,
    ) {}

    /**
     * Create a new workflow step.
     */
    public static function create(
        ActionType $actionType,
        int $order = 0,
        ?string $name = null,
        ?ActionConfig $actionConfig = null,
        ?StepConditions $conditions = null,
        ?string $branchId = null,
        bool $isParallel = false,
        bool $continueOnError = false,
        int $retryCount = 0,
        int $retryDelaySeconds = 60,
    ): self {
        return new self(
            id: null,
            workflowId: null,
            order: $order,
            name: $name,
            actionType: $actionType,
            actionConfig: $actionConfig ?? new ActionConfig(),
            conditions: $conditions ?? new StepConditions(),
            branchId: $branchId,
            isParallel: $isParallel,
            continueOnError: $continueOnError,
            retryCount: $retryCount,
            retryDelaySeconds: $retryDelaySeconds,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        int $workflowId,
        int $order,
        ?string $name,
        ActionType $actionType,
        ActionConfig $actionConfig,
        StepConditions $conditions,
        ?string $branchId,
        bool $isParallel,
        bool $continueOnError,
        int $retryCount,
        int $retryDelaySeconds,
    ): self {
        return new self(
            id: $id,
            workflowId: $workflowId,
            order: $order,
            name: $name,
            actionType: $actionType,
            actionConfig: $actionConfig,
            conditions: $conditions,
            branchId: $branchId,
            isParallel: $isParallel,
            continueOnError: $continueOnError,
            retryCount: $retryCount,
            retryDelaySeconds: $retryDelaySeconds,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Update step details.
     */
    public function update(
        ActionType $actionType,
        int $order,
        ?string $name,
        ActionConfig $actionConfig,
        StepConditions $conditions,
        ?string $branchId,
        bool $isParallel,
        bool $continueOnError,
        int $retryCount,
        int $retryDelaySeconds,
    ): void {
        $this->actionType = $actionType;
        $this->order = $order;
        $this->name = $name;
        $this->actionConfig = $actionConfig;
        $this->conditions = $conditions;
        $this->branchId = $branchId;
        $this->isParallel = $isParallel;
        $this->continueOnError = $continueOnError;
        $this->retryCount = $retryCount;
        $this->retryDelaySeconds = $retryDelaySeconds;
    }

    /**
     * Assign to a workflow.
     */
    public function assignToWorkflow(int $workflowId): void
    {
        $this->workflowId = $workflowId;
    }

    /**
     * Update the order/position.
     */
    public function reorder(int $newOrder): void
    {
        $this->order = $newOrder;
    }

    /**
     * Check if this step should be executed based on its conditions.
     */
    public function hasConditions(): bool
    {
        return $this->conditions->hasConditions();
    }

    /**
     * Check if this is a flow control step.
     */
    public function isFlowControl(): bool
    {
        return $this->actionType->isFlowControl();
    }

    /**
     * Check if this step affects records.
     */
    public function affectsRecords(): bool
    {
        return $this->actionType->affectsRecords();
    }

    /**
     * Get a description of what this step does.
     */
    public function getDescription(): string
    {
        return $this->name ?? $this->actionType->label();
    }

    // ========== Entity Implementation ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->id !== null && $this->id === $other->id;
    }

    // ========== Getters ==========

    public function workflowId(): ?int
    {
        return $this->workflowId;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function actionType(): ActionType
    {
        return $this->actionType;
    }

    public function actionConfig(): ActionConfig
    {
        return $this->actionConfig;
    }

    public function conditions(): StepConditions
    {
        return $this->conditions;
    }

    public function branchId(): ?string
    {
        return $this->branchId;
    }

    public function isParallel(): bool
    {
        return $this->isParallel;
    }

    public function continueOnError(): bool
    {
        return $this->continueOnError;
    }

    public function retryCount(): int
    {
        return $this->retryCount;
    }

    public function retryDelaySeconds(): int
    {
        return $this->retryDelaySeconds;
    }

    /**
     * Check if this step should retry on failure.
     */
    public function shouldRetry(): bool
    {
        return $this->retryCount > 0;
    }
}
