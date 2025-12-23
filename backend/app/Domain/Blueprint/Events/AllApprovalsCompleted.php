<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when all required approvals for a transition have been completed.
 */
final class AllApprovalsCompleted extends DomainEvent
{
    public function __construct(
        private readonly int $executionId,
        private readonly int $blueprintId,
        private readonly int $transitionId,
        private readonly int $recordId,
        private readonly int $fromStateId,
        private readonly int $toStateId,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int
    {
        return $this->executionId;
    }

    public function aggregateType(): string
    {
        return 'BlueprintTransitionExecution';
    }

    public function executionId(): int
    {
        return $this->executionId;
    }

    public function blueprintId(): int
    {
        return $this->blueprintId;
    }

    public function transitionId(): int
    {
        return $this->transitionId;
    }

    public function recordId(): int
    {
        return $this->recordId;
    }

    public function fromStateId(): int
    {
        return $this->fromStateId;
    }

    public function toStateId(): int
    {
        return $this->toStateId;
    }

    public function toPayload(): array
    {
        return [
            'execution_id' => $this->executionId,
            'blueprint_id' => $this->blueprintId,
            'transition_id' => $this->transitionId,
            'record_id' => $this->recordId,
            'from_state_id' => $this->fromStateId,
            'to_state_id' => $this->toStateId,
        ];
    }
}
