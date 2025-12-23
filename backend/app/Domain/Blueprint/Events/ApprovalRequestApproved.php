<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a single approval request is approved.
 */
final class ApprovalRequestApproved extends DomainEvent
{
    public function __construct(
        private readonly int $approvalRequestId,
        private readonly int $blueprintId,
        private readonly int $transitionId,
        private readonly int $recordId,
        private readonly int $executionId,
        private readonly int $approvedByUserId,
        private readonly ?string $comment,
        private readonly bool $requireAll,
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

    public function approvedByUserId(): int
    {
        return $this->approvedByUserId;
    }

    public function comment(): ?string
    {
        return $this->comment;
    }

    public function requireAll(): bool
    {
        return $this->requireAll;
    }

    public function toPayload(): array
    {
        return [
            'approval_request_id' => $this->approvalRequestId,
            'blueprint_id' => $this->blueprintId,
            'transition_id' => $this->transitionId,
            'record_id' => $this->recordId,
            'execution_id' => $this->executionId,
            'approved_by_user_id' => $this->approvedByUserId,
            'comment' => $this->comment,
            'require_all' => $this->requireAll,
        ];
    }
}
