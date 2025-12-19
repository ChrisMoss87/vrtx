<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a workflow step is executed successfully.
 */
final class StepExecuted extends DomainEvent
{
    /**
     * @param array<string, mixed> $outputData
     */
    public function __construct(
        private readonly int $workflowId,
        private readonly int $executionId,
        private readonly int $stepId,
        private readonly string $actionType,
        private readonly int $durationMs,
        private readonly array $outputData,
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

    public function durationMs(): int
    {
        return $this->durationMs;
    }

    /**
     * @return array<string, mixed>
     */
    public function outputData(): array
    {
        return $this->outputData;
    }

    public function toPayload(): array
    {
        return [
            'workflow_id' => $this->workflowId,
            'execution_id' => $this->executionId,
            'step_id' => $this->stepId,
            'action_type' => $this->actionType,
            'duration_ms' => $this->durationMs,
            'output_data' => $this->outputData,
        ];
    }
}
