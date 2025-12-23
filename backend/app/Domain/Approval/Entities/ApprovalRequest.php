<?php

declare(strict_types=1);

namespace App\Domain\Approval\Entities;

use App\Domain\Approval\ValueObjects\ApprovalStatus;
use App\Domain\Approval\ValueObjects\ApprovalType;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;

/**
 * ApprovalRequest domain entity representing an approval workflow request.
 *
 * Approval requests track multi-step approval processes with sequential or
 * parallel approvers, supporting features like expiration, cancellation,
 * and audit history.
 */
final class ApprovalRequest implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private string $uuid,
        private ?int $ruleId,
        private string $entityType,
        private int $entityId,
        private ?string $title,
        private ?string $description,
        private ApprovalStatus $status,
        private ApprovalType $type,
        private array $snapshotData,
        private ?string $value,
        private string $currency,
        private ?DateTimeImmutable $submittedAt,
        private ?DateTimeImmutable $completedAt,
        private ?DateTimeImmutable $expiresAt,
        private ?int $requestedBy,
        private ?int $finalApproverId,
        private ?string $finalComments,
        private array $approverIds,
        private int $currentStep,
        private int $totalSteps,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Create a new approval request.
     */
    public static function create(
        string $uuid,
        string $entityType,
        int $entityId,
        ApprovalType $type,
        array $approverIds,
        ?int $requestedBy = null,
        ?string $title = null,
        ?string $description = null,
        ?int $ruleId = null,
    ): self {
        return new self(
            id: null,
            uuid: $uuid,
            ruleId: $ruleId,
            entityType: $entityType,
            entityId: $entityId,
            title: $title,
            description: $description,
            status: ApprovalStatus::Pending,
            type: $type,
            snapshotData: [],
            value: null,
            currency: 'USD',
            submittedAt: null,
            completedAt: null,
            expiresAt: null,
            requestedBy: $requestedBy,
            finalApproverId: null,
            finalComments: null,
            approverIds: $approverIds,
            currentStep: 1,
            totalSteps: count($approverIds),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute an approval request from persistence.
     */
    public static function reconstitute(
        int $id,
        string $uuid,
        ?int $ruleId,
        string $entityType,
        int $entityId,
        ?string $title,
        ?string $description,
        ApprovalStatus $status,
        ApprovalType $type,
        array $snapshotData,
        ?string $value,
        string $currency,
        ?DateTimeImmutable $submittedAt,
        ?DateTimeImmutable $completedAt,
        ?DateTimeImmutable $expiresAt,
        ?int $requestedBy,
        ?int $finalApproverId,
        ?string $finalComments,
        array $approverIds,
        int $currentStep,
        int $totalSteps,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            uuid: $uuid,
            ruleId: $ruleId,
            entityType: $entityType,
            entityId: $entityId,
            title: $title,
            description: $description,
            status: $status,
            type: $type,
            snapshotData: $snapshotData,
            value: $value,
            currency: $currency,
            submittedAt: $submittedAt,
            completedAt: $completedAt,
            expiresAt: $expiresAt,
            requestedBy: $requestedBy,
            finalApproverId: $finalApproverId,
            finalComments: $finalComments,
            approverIds: $approverIds,
            currentStep: $currentStep,
            totalSteps: $totalSteps,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // -------------------------------------------------------------------------
    // Business Logic Methods
    // -------------------------------------------------------------------------

    /**
     * Check if request is pending action.
     */
    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    /**
     * Check if request was approved.
     */
    public function isApproved(): bool
    {
        return $this->status->isApproved();
    }

    /**
     * Check if request was rejected.
     */
    public function isRejected(): bool
    {
        return $this->status->isRejected();
    }

    /**
     * Check if request has reached a terminal state.
     */
    public function isComplete(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Check if request can be actioned (approved/rejected).
     */
    public function canBeActioned(): bool
    {
        return $this->status->canBeActioned();
    }

    /**
     * Check if request can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    /**
     * Check if request has expired.
     */
    public function hasExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable();
    }

    /**
     * Check if all steps have been approved.
     */
    public function allStepsApproved(): bool
    {
        return $this->currentStep > $this->totalSteps;
    }

    /**
     * Submit the approval request for processing.
     *
     * @return self Returns a new instance in submitted state
     * @throws \DomainException If request cannot be submitted
     */
    public function submit(): self
    {
        if ($this->status !== ApprovalStatus::Pending) {
            throw new \DomainException("Can only submit pending requests");
        }

        return new self(
            id: $this->id,
            uuid: $this->uuid,
            ruleId: $this->ruleId,
            entityType: $this->entityType,
            entityId: $this->entityId,
            title: $this->title,
            description: $this->description,
            status: ApprovalStatus::InProgress,
            type: $this->type,
            snapshotData: $this->snapshotData,
            value: $this->value,
            currency: $this->currency,
            submittedAt: new DateTimeImmutable(),
            completedAt: $this->completedAt,
            expiresAt: $this->expiresAt,
            requestedBy: $this->requestedBy,
            finalApproverId: $this->finalApproverId,
            finalComments: $this->finalComments,
            approverIds: $this->approverIds,
            currentStep: $this->currentStep,
            totalSteps: $this->totalSteps,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Approve the current step.
     *
     * @return self Returns a new instance with approval recorded
     * @throws \DomainException If request cannot be approved
     */
    public function approve(int $approverId, ?string $comments = null): self
    {
        if (!$this->canBeActioned()) {
            throw new \DomainException("Cannot approve request in status: {$this->status->value}");
        }

        $newCurrentStep = $this->currentStep;
        $newStatus = $this->status;
        $completedAt = $this->completedAt;
        $finalApproverId = $this->finalApproverId;
        $finalComments = $this->finalComments;

        if ($this->type->requiresAll()) {
            $newCurrentStep++;
            if ($newCurrentStep > $this->totalSteps) {
                $newStatus = ApprovalStatus::Approved;
                $completedAt = new DateTimeImmutable();
                $finalApproverId = $approverId;
                $finalComments = $comments;
            }
        } else {
            // Any approver can approve
            $newStatus = ApprovalStatus::Approved;
            $completedAt = new DateTimeImmutable();
            $finalApproverId = $approverId;
            $finalComments = $comments;
        }

        return new self(
            id: $this->id,
            uuid: $this->uuid,
            ruleId: $this->ruleId,
            entityType: $this->entityType,
            entityId: $this->entityId,
            title: $this->title,
            description: $this->description,
            status: $newStatus,
            type: $this->type,
            snapshotData: $this->snapshotData,
            value: $this->value,
            currency: $this->currency,
            submittedAt: $this->submittedAt,
            completedAt: $completedAt,
            expiresAt: $this->expiresAt,
            requestedBy: $this->requestedBy,
            finalApproverId: $finalApproverId,
            finalComments: $finalComments,
            approverIds: $this->approverIds,
            currentStep: $newCurrentStep,
            totalSteps: $this->totalSteps,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Reject the request.
     *
     * @return self Returns a new instance with rejection recorded
     * @throws \DomainException If request cannot be rejected
     */
    public function reject(int $approverId, ?string $comments = null): self
    {
        if (!$this->canBeActioned()) {
            throw new \DomainException("Cannot reject request in status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            uuid: $this->uuid,
            ruleId: $this->ruleId,
            entityType: $this->entityType,
            entityId: $this->entityId,
            title: $this->title,
            description: $this->description,
            status: ApprovalStatus::Rejected,
            type: $this->type,
            snapshotData: $this->snapshotData,
            value: $this->value,
            currency: $this->currency,
            submittedAt: $this->submittedAt,
            completedAt: new DateTimeImmutable(),
            expiresAt: $this->expiresAt,
            requestedBy: $this->requestedBy,
            finalApproverId: $approverId,
            finalComments: $comments,
            approverIds: $this->approverIds,
            currentStep: $this->currentStep,
            totalSteps: $this->totalSteps,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Cancel the request.
     *
     * @return self Returns a new instance with cancelled status
     * @throws \DomainException If request cannot be cancelled
     */
    public function cancel(?string $reason = null): self
    {
        if (!$this->canBeCancelled()) {
            throw new \DomainException("Cannot cancel request in status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            uuid: $this->uuid,
            ruleId: $this->ruleId,
            entityType: $this->entityType,
            entityId: $this->entityId,
            title: $this->title,
            description: $this->description,
            status: ApprovalStatus::Cancelled,
            type: $this->type,
            snapshotData: $this->snapshotData,
            value: $this->value,
            currency: $this->currency,
            submittedAt: $this->submittedAt,
            completedAt: new DateTimeImmutable(),
            expiresAt: $this->expiresAt,
            requestedBy: $this->requestedBy,
            finalApproverId: $this->finalApproverId,
            finalComments: $reason,
            approverIds: $this->approverIds,
            currentStep: $this->currentStep,
            totalSteps: $this->totalSteps,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Mark the request as expired.
     *
     * @return self Returns a new instance with expired status
     */
    public function expire(): self
    {
        if (!$this->isPending()) {
            return $this; // Already complete, no change
        }

        return new self(
            id: $this->id,
            uuid: $this->uuid,
            ruleId: $this->ruleId,
            entityType: $this->entityType,
            entityId: $this->entityId,
            title: $this->title,
            description: $this->description,
            status: ApprovalStatus::Expired,
            type: $this->type,
            snapshotData: $this->snapshotData,
            value: $this->value,
            currency: $this->currency,
            submittedAt: $this->submittedAt,
            completedAt: new DateTimeImmutable(),
            expiresAt: $this->expiresAt,
            requestedBy: $this->requestedBy,
            finalApproverId: $this->finalApproverId,
            finalComments: 'Expired due to timeout',
            approverIds: $this->approverIds,
            currentStep: $this->currentStep,
            totalSteps: $this->totalSteps,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Advance to the next step.
     *
     * @return self Returns a new instance with incremented step
     */
    public function advanceToNextStep(): self
    {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            ruleId: $this->ruleId,
            entityType: $this->entityType,
            entityId: $this->entityId,
            title: $this->title,
            description: $this->description,
            status: $this->status,
            type: $this->type,
            snapshotData: $this->snapshotData,
            value: $this->value,
            currency: $this->currency,
            submittedAt: $this->submittedAt,
            completedAt: $this->completedAt,
            expiresAt: $this->expiresAt,
            requestedBy: $this->requestedBy,
            finalApproverId: $this->finalApproverId,
            finalComments: $this->finalComments,
            approverIds: $this->approverIds,
            currentStep: $this->currentStep + 1,
            totalSteps: $this->totalSteps,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Set snapshot data for audit purposes.
     *
     * @return self Returns a new instance with snapshot data
     */
    public function withSnapshotData(array $snapshotData): self
    {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            ruleId: $this->ruleId,
            entityType: $this->entityType,
            entityId: $this->entityId,
            title: $this->title,
            description: $this->description,
            status: $this->status,
            type: $this->type,
            snapshotData: $snapshotData,
            value: $this->value,
            currency: $this->currency,
            submittedAt: $this->submittedAt,
            completedAt: $this->completedAt,
            expiresAt: $this->expiresAt,
            requestedBy: $this->requestedBy,
            finalApproverId: $this->finalApproverId,
            finalComments: $this->finalComments,
            approverIds: $this->approverIds,
            currentStep: $this->currentStep,
            totalSteps: $this->totalSteps,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Set value information.
     *
     * @return self Returns a new instance with value
     */
    public function withValue(string $value, string $currency = 'USD'): self
    {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            ruleId: $this->ruleId,
            entityType: $this->entityType,
            entityId: $this->entityId,
            title: $this->title,
            description: $this->description,
            status: $this->status,
            type: $this->type,
            snapshotData: $this->snapshotData,
            value: $value,
            currency: $currency,
            submittedAt: $this->submittedAt,
            completedAt: $this->completedAt,
            expiresAt: $this->expiresAt,
            requestedBy: $this->requestedBy,
            finalApproverId: $this->finalApproverId,
            finalComments: $this->finalComments,
            approverIds: $this->approverIds,
            currentStep: $this->currentStep,
            totalSteps: $this->totalSteps,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Set expiration time.
     *
     * @return self Returns a new instance with expiration
     */
    public function withExpiration(DateTimeImmutable $expiresAt): self
    {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            ruleId: $this->ruleId,
            entityType: $this->entityType,
            entityId: $this->entityId,
            title: $this->title,
            description: $this->description,
            status: $this->status,
            type: $this->type,
            snapshotData: $this->snapshotData,
            value: $this->value,
            currency: $this->currency,
            submittedAt: $this->submittedAt,
            completedAt: $this->completedAt,
            expiresAt: $expiresAt,
            requestedBy: $this->requestedBy,
            finalApproverId: $this->finalApproverId,
            finalComments: $this->finalComments,
            approverIds: $this->approverIds,
            currentStep: $this->currentStep,
            totalSteps: $this->totalSteps,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getRuleId(): ?int
    {
        return $this->ruleId;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStatus(): ApprovalStatus
    {
        return $this->status;
    }

    public function getType(): ApprovalType
    {
        return $this->type;
    }

    public function getSnapshotData(): array
    {
        return $this->snapshotData;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getSubmittedAt(): ?DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getRequestedBy(): ?int
    {
        return $this->requestedBy;
    }

    public function getFinalApproverId(): ?int
    {
        return $this->finalApproverId;
    }

    public function getFinalComments(): ?string
    {
        return $this->finalComments;
    }

    public function getApproverIds(): array
    {
        return $this->approverIds;
    }

    public function getCurrentStep(): int
    {
        return $this->currentStep;
    }

    public function getTotalSteps(): int
    {
        return $this->totalSteps;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
