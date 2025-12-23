<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a single approval request is rejected.
 */
final class ApprovalRequestRejected extends DomainEvent
{
    public function __construct(
        private readonly int $approvalRequestId,
        private readonly int $blueprintId,
        private readonly int $transitionId,
        private readonly int $recordId,
        private readonly int $executionId,
        private readonly int $rejectedByUserId,
        private readonly ?string $comment,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int
    {
        return $this->approvalRequestId;
    }

    public function aggregateType(): string
    {
        return 'BlueprintApprovalRequest';
    }

    public function approvalRequestId(): int
    {
        return $this->approvalRequestId;
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

    public function executionId(): int
    {
        return $this->executionId;
    }

    public function rejectedByUserId(): int
    {
        return $this->rejectedByUserId;
    }

    public function comment(): ?string
    {
        return $this->comment;
    }

    public function toPayload(): array
    {
        return [
            'approval_request_id' => $this->approvalRequestId,
            'blueprint_id' => $this->blueprintId,
            'transition_id' => $this->transitionId,
            'record_id' => $this->recordId,
            'execution_id' => $this->executionId,
            'rejected_by_user_id' => $this->rejectedByUserId,
            'comment' => $this->comment,
        ];
    }
}
