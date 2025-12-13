<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a workflow is triggered for execution.
 */
final class WorkflowTriggered extends DomainEvent
{
    public function __construct(
        private readonly int $workflowId,
        private readonly int $executionId,
        private readonly string $triggerType,
        private readonly ?int $recordId,
        private readonly ?string $recordType,
        private readonly ?int $triggeredByUserId,
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

    public function triggerType(): string
    {
        return $this->triggerType;
    }

    public function recordId(): ?int
    {
        return $this->recordId;
    }

    public function recordType(): ?string
    {
        return $this->recordType;
    }

    public function triggeredByUserId(): ?int
    {
        return $this->triggeredByUserId;
    }

    public function toPayload(): array
    {
        return [
            'workflow_id' => $this->workflowId,
            'execution_id' => $this->executionId,
            'trigger_type' => $this->triggerType,
            'record_id' => $this->recordId,
            'record_type' => $this->recordType,
            'triggered_by_user_id' => $this->triggeredByUserId,
        ];
    }
}
