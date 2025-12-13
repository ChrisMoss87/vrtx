<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a workflow step fails.
 */
final class StepFailed extends DomainEvent
{
    public function __construct(
        private readonly int $workflowId,
        private readonly int $executionId,
        private readonly int $stepId,
        private readonly string $actionType,
        private readonly string $errorMessage,
        private readonly int $attemptNumber,
        private readonly bool $willRetry,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int
    {
        return $this->executionId;
    }

    public function aggregateType(): string
    {
        return 'WorkflowExecution';
    }

    public function workflowId(): int
    {
        return $this->workflowId;
    }

    public function executionId(): int
    {
        return $this->executionId;
    }

    public function stepId(): int
    {
        return $this->stepId;
    }

    public function actionType(): string
    {
        return $this->actionType;
    }

    public function errorMessage(): string
    {
        return $this->errorMessage;
    }

    public function attemptNumber(): int
    {
        return $this->attemptNumber;
    }

    public function willRetry(): bool
    {
        return $this->willRetry;
    }

    public function toPayload(): array
    {
        return [
            'workflow_id' => $this->workflowId,
            'execution_id' => $this->executionId,
            'step_id' => $this->stepId,
            'action_type' => $this->actionType,
            'error_message' => $this->errorMessage,
            'attempt_number' => $this->attemptNumber,
            'will_retry' => $this->willRetry,
        ];
    }
}
