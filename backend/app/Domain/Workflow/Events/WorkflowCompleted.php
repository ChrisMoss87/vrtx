<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a workflow execution completes successfully.
 */
final class WorkflowCompleted extends DomainEvent
{
    public function __construct(
        private readonly int $workflowId,
        private readonly int $executionId,
        private readonly int $stepsCompleted,
        private readonly int $stepsSkipped,
        private readonly int $durationMs,
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

    public function stepsCompleted(): int
    {
        return $this->stepsCompleted;
    }

    public function stepsSkipped(): int
    {
        return $this->stepsSkipped;
    }

    public function durationMs(): int
    {
        return $this->durationMs;
    }

    public function toPayload(): array
    {
        return [
            'workflow_id' => $this->workflowId,
            'execution_id' => $this->executionId,
            'steps_completed' => $this->stepsCompleted,
            'steps_skipped' => $this->stepsSkipped,
            'duration_ms' => $this->durationMs,
        ];
    }
}
