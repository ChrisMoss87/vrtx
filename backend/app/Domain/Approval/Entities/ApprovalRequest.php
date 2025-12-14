<?php

declare(strict_types=1);

namespace App\Domain\Approval\Entities;

use App\Domain\Approval\ValueObjects\ApprovalStatus;
use App\Domain\Approval\ValueObjects\ApprovalType;

class ApprovalRequest
{
    private ?int $id = null;
    private int $moduleId;
    private int $recordId;
    private ?int $ruleId;
    private ApprovalType $type;
    private ApprovalStatus $status;
    private ?int $requestedBy;
    private ?string $requestReason;
    private array $approverIds;
    private int $currentStep;
    private int $totalSteps;
    private ?\DateTimeImmutable $dueAt;
    private ?\DateTimeImmutable $completedAt;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;
    /** @var ApprovalStep[] */
    private array $steps = [];

    private function __construct(
        int $moduleId,
        int $recordId,
        ApprovalType $type,
        array $approverIds,
    ) {
        $this->moduleId = $moduleId;
        $this->recordId = $recordId;
        $this->type = $type;
        $this->approverIds = $approverIds;
        $this->status = ApprovalStatus::PENDING;
        $this->ruleId = null;
        $this->requestedBy = null;
        $this->requestReason = null;
        $this->currentStep = 1;
        $this->totalSteps = count($approverIds);
        $this->dueAt = null;
        $this->completedAt = null;
    }

    public static function create(
        int $moduleId,
        int $recordId,
        ApprovalType $type,
        array $approverIds,
        ?int $requestedBy = null,
        ?string $reason = null,
    ): self {
        $request = new self($moduleId, $recordId, $type, $approverIds);
        $request->requestedBy = $requestedBy;
        $request->requestReason = $reason;
        return $request;
    }

    public static function reconstitute(
        int $id,
        int $moduleId,
        int $recordId,
        ?int $ruleId,
        ApprovalType $type,
        ApprovalStatus $status,
        ?int $requestedBy,
        ?string $requestReason,
        array $approverIds,
        int $currentStep,
        int $totalSteps,
        ?\DateTimeImmutable $dueAt,
        ?\DateTimeImmutable $completedAt,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $request = new self($moduleId, $recordId, $type, $approverIds);
        $request->id = $id;
        $request->ruleId = $ruleId;
        $request->status = $status;
        $request->requestedBy = $requestedBy;
        $request->requestReason = $requestReason;
        $request->currentStep = $currentStep;
        $request->totalSteps = $totalSteps;
        $request->dueAt = $dueAt;
        $request->completedAt = $completedAt;
        $request->createdAt = $createdAt;
        $request->updatedAt = $updatedAt;
        return $request;
    }

    public function getId(): ?int { return $this->id; }
    public function getModuleId(): int { return $this->moduleId; }
    public function getRecordId(): int { return $this->recordId; }
    public function getRuleId(): ?int { return $this->ruleId; }
    public function getType(): ApprovalType { return $this->type; }
    public function getStatus(): ApprovalStatus { return $this->status; }
    public function getApproverIds(): array { return $this->approverIds; }
    public function getCurrentStep(): int { return $this->currentStep; }
    public function getTotalSteps(): int { return $this->totalSteps; }
    public function getSteps(): array { return $this->steps; }

    public function setSteps(array $steps): void
    {
        $this->steps = $steps;
    }

    public function approve(int $approverId, ?string $comment = null): void
    {
        if (!$this->status->isPending()) {
            throw new \RuntimeException('Cannot approve a non-pending request');
        }

        if ($this->type->requiresAll()) {
            $this->currentStep++;
            if ($this->currentStep > $this->totalSteps) {
                $this->complete(ApprovalStatus::APPROVED);
            }
        } else {
            $this->complete(ApprovalStatus::APPROVED);
        }

        $this->updatedAt = new \DateTimeImmutable();
    }

    public function reject(int $approverId, ?string $comment = null): void
    {
        if (!$this->status->isPending()) {
            throw new \RuntimeException('Cannot reject a non-pending request');
        }

        $this->complete(ApprovalStatus::REJECTED);
    }

    public function cancel(): void
    {
        $this->complete(ApprovalStatus::CANCELLED);
    }

    public function expire(): void
    {
        if ($this->status->isPending()) {
            $this->complete(ApprovalStatus::EXPIRED);
        }
    }

    private function complete(ApprovalStatus $status): void
    {
        $this->status = $status;
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isApproved(): bool
    {
        return $this->status === ApprovalStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === ApprovalStatus::REJECTED;
    }
}
