<?php

declare(strict_types=1);

namespace App\Domain\Approval\Entities;

use App\Domain\Approval\ValueObjects\StepStatus;
use App\Domain\Shared\Contracts\Entity;

final class ApprovalStep implements Entity
{
    private function __construct(
        private ?int $id,
        private int $requestId,
        private ?int $approverId,
        private ?int $roleId,
        private string $approverType,
        private int $stepOrder,
        private StepStatus $status,
        private bool $isCurrent,
        private ?string $comments,
        private ?\DateTimeImmutable $respondedAt,
        private ?\DateTimeImmutable $dueAt,
        private ?int $delegatedToId,
        private ?int $delegatedById,
        private ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $requestId,
        int $stepOrder,
        ?int $approverId = null,
        ?int $roleId = null,
        string $approverType = 'user',
    ): self {
        return new self(
            id: null,
            requestId: $requestId,
            approverId: $approverId,
            roleId: $roleId,
            approverType: $approverType,
            stepOrder: $stepOrder,
            status: StepStatus::PENDING,
            isCurrent: false,
            comments: null,
            respondedAt: null,
            dueAt: null,
            delegatedToId: null,
            delegatedById: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $requestId,
        ?int $approverId,
        ?int $roleId,
        string $approverType,
        int $stepOrder,
        StepStatus $status,
        bool $isCurrent,
        ?string $comments,
        ?\DateTimeImmutable $respondedAt,
        ?\DateTimeImmutable $dueAt,
        ?int $delegatedToId,
        ?int $delegatedById,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            requestId: $requestId,
            approverId: $approverId,
            roleId: $roleId,
            approverType: $approverType,
            stepOrder: $stepOrder,
            status: $status,
            isCurrent: $isCurrent,
            comments: $comments,
            respondedAt: $respondedAt,
            dueAt: $dueAt,
            delegatedToId: $delegatedToId,
            delegatedById: $delegatedById,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getRequestId(): int { return $this->requestId; }
    public function getApproverId(): ?int { return $this->approverId; }
    public function getRoleId(): ?int { return $this->roleId; }
    public function getApproverType(): string { return $this->approverType; }
    public function getStepOrder(): int { return $this->stepOrder; }
    public function getStatus(): StepStatus { return $this->status; }
    public function isCurrent(): bool { return $this->isCurrent; }
    public function getComments(): ?string { return $this->comments; }
    public function getRespondedAt(): ?\DateTimeImmutable { return $this->respondedAt; }
    public function getDueAt(): ?\DateTimeImmutable { return $this->dueAt; }
    public function getDelegatedToId(): ?int { return $this->delegatedToId; }
    public function getDelegatedById(): ?int { return $this->delegatedById; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    // Get effective approver (considering delegation)
    public function getEffectiveApproverId(): ?int
    {
        return $this->delegatedToId ?? $this->approverId;
    }

    // Domain actions
    public function approve(?string $comments = null): void
    {
        if (!$this->isPending()) {
            throw new \RuntimeException('Cannot approve a non-pending step');
        }

        $this->status = StepStatus::APPROVED;
        $this->comments = $comments;
        $this->respondedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function reject(?string $comments = null): void
    {
        if (!$this->isPending()) {
            throw new \RuntimeException('Cannot reject a non-pending step');
        }

        $this->status = StepStatus::REJECTED;
        $this->comments = $comments;
        $this->respondedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function skip(?string $reason = null): void
    {
        if (!$this->isPending()) {
            throw new \RuntimeException('Cannot skip a non-pending step');
        }

        $this->status = StepStatus::SKIPPED;
        $this->comments = $reason;
        $this->respondedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function delegate(int $delegateId, int $delegatedById): void
    {
        $this->delegatedToId = $delegateId;
        $this->delegatedById = $delegatedById;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function activate(?\DateTimeImmutable $dueAt = null): void
    {
        $this->isCurrent = true;
        $this->dueAt = $dueAt;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isCurrent = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    // State checks
    public function isPending(): bool
    {
        return $this->status === StepStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === StepStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === StepStatus::REJECTED;
    }

    public function isDecided(): bool
    {
        return $this->status->isDecided();
    }

    public function isOverdue(): bool
    {
        if ($this->dueAt === null || !$this->isPending()) {
            return false;
        }

        return $this->dueAt < new \DateTimeImmutable();
    }

    public function isDelegated(): bool
    {
        return $this->delegatedToId !== null;
    }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->id !== null
            && $other->id !== null
            && $this->id === $other->id;
    }
}
