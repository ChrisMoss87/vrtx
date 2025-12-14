<?php

declare(strict_types=1);

namespace App\Domain\Approval\Entities;

use App\Domain\Approval\ValueObjects\ApprovalStatus;

class ApprovalStep
{
    private ?int $id = null;
    private int $requestId;
    private int $stepOrder;
    private int $approverId;
    private ApprovalStatus $status;
    private ?string $comment;
    private ?\DateTimeImmutable $respondedAt;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    private function __construct(int $requestId, int $stepOrder, int $approverId)
    {
        $this->requestId = $requestId;
        $this->stepOrder = $stepOrder;
        $this->approverId = $approverId;
        $this->status = ApprovalStatus::PENDING;
        $this->comment = null;
        $this->respondedAt = null;
    }

    public static function create(int $requestId, int $stepOrder, int $approverId): self
    {
        return new self($requestId, $stepOrder, $approverId);
    }

    public static function reconstitute(
        int $id,
        int $requestId,
        int $stepOrder,
        int $approverId,
        ApprovalStatus $status,
        ?string $comment,
        ?\DateTimeImmutable $respondedAt,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $step = new self($requestId, $stepOrder, $approverId);
        $step->id = $id;
        $step->status = $status;
        $step->comment = $comment;
        $step->respondedAt = $respondedAt;
        $step->createdAt = $createdAt;
        $step->updatedAt = $updatedAt;
        return $step;
    }

    public function getId(): ?int { return $this->id; }
    public function getRequestId(): int { return $this->requestId; }
    public function getStepOrder(): int { return $this->stepOrder; }
    public function getApproverId(): int { return $this->approverId; }
    public function getStatus(): ApprovalStatus { return $this->status; }
    public function getComment(): ?string { return $this->comment; }
    public function getRespondedAt(): ?\DateTimeImmutable { return $this->respondedAt; }

    public function approve(?string $comment = null): void
    {
        $this->status = ApprovalStatus::APPROVED;
        $this->comment = $comment;
        $this->respondedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function reject(?string $comment = null): void
    {
        $this->status = ApprovalStatus::REJECTED;
        $this->comment = $comment;
        $this->respondedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function delegate(int $newApproverId): void
    {
        $this->approverId = $newApproverId;
        $this->status = ApprovalStatus::DELEGATED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }
}
