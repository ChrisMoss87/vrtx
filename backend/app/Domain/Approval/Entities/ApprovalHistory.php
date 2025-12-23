<?php

declare(strict_types=1);

namespace App\Domain\Approval\Entities;

use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class ApprovalHistory implements Entity
{
    // Action constants
    public const ACTION_SUBMITTED = 'submitted';
    public const ACTION_APPROVED = 'approved';
    public const ACTION_REJECTED = 'rejected';
    public const ACTION_DELEGATED = 'delegated';
    public const ACTION_ESCALATED = 'escalated';
    public const ACTION_COMMENTED = 'commented';
    public const ACTION_RECALLED = 'recalled';
    public const ACTION_CANCELLED = 'cancelled';
    public const ACTION_STEP_APPROVED = 'step_approved';
    public const ACTION_STEP_REJECTED = 'step_rejected';
    public const ACTION_STEP_SKIPPED = 'step_skipped';

    private function __construct(
        private ?int $id,
        private int $requestId,
        private ?int $stepId,
        private ?int $userId,
        private string $action,
        private ?string $comments,
        private array $changes,
        private ?string $ipAddress,
        private ?DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        int $requestId,
        string $action,
        ?int $stepId = null,
        ?int $userId = null,
        ?string $comments = null,
        array $changes = [],
        ?string $ipAddress = null,
    ): self {
        return new self(
            id: null,
            requestId: $requestId,
            stepId: $stepId,
            userId: $userId,
            action: $action,
            comments: $comments,
            changes: $changes,
            ipAddress: $ipAddress,
            createdAt: new DateTimeImmutable(),
        );
    }

    public static function reconstitute(
        int $id,
        int $requestId,
        ?int $stepId,
        ?int $userId,
        string $action,
        ?string $comments,
        array $changes,
        ?string $ipAddress,
        ?DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            requestId: $requestId,
            stepId: $stepId,
            userId: $userId,
            action: $action,
            comments: $comments,
            changes: $changes,
            ipAddress: $ipAddress,
            createdAt: $createdAt,
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getRequestId(): int { return $this->requestId; }
    public function getStepId(): ?int { return $this->stepId; }
    public function getUserId(): ?int { return $this->userId; }
    public function getAction(): string { return $this->action; }
    public function getComments(): ?string { return $this->comments; }
    public function getChanges(): array { return $this->changes; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }

    public function getActionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_SUBMITTED => 'Submitted for approval',
            self::ACTION_APPROVED => 'Approved',
            self::ACTION_REJECTED => 'Rejected',
            self::ACTION_DELEGATED => 'Delegated',
            self::ACTION_ESCALATED => 'Escalated',
            self::ACTION_COMMENTED => 'Comment added',
            self::ACTION_RECALLED => 'Recalled',
            self::ACTION_CANCELLED => 'Cancelled',
            self::ACTION_STEP_APPROVED => 'Step approved',
            self::ACTION_STEP_REJECTED => 'Step rejected',
            self::ACTION_STEP_SKIPPED => 'Step skipped',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
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
