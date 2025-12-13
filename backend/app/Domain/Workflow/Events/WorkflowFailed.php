<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a workflow execution fails.
 */
final class WorkflowFailed extends DomainEvent
{
    public function __construct(
        private readonly int $workflowId,
        private readonly int $executionId,
        private readonly string $errorMessage,
        private readonly int $stepsCompleted,
        private readonly int $stepsFailed,
        private readonly ?int $failedStepId,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int
    {
        return $this->workflowId;
    }

    public function aggregateType(): string
    {
        return 'Workflow';
    }

    public function workflowId(): int
    {
        return $this->workflowId;
    }

    public function executionId(): int
    {
        return $this->executionId;
    }

    public function errorMessage(): string
    {
        return $this->errorMessage;
    }

    public function stepsCompleted(): int
    {
        return $this->stepsCompleted;
    }

    public function stepsFailed(): int
    {
        return $this->stepsFailed;
    }

    public function failedStepId(): ?int
    {
        return $this->failedStepId;
    }

    public function toPayload(): array
    {
        return [
            'workflow_id' => $this->workflowId,
            'execution_id' => $this->executionId,
            'error_message' => $this->errorMessage,
            'steps_completed' => $this->stepsCompleted,
            'steps_failed' => $this->stepsFailed,
            'failed_step_id' => $this->failedStepId,
        ];
    }
}
