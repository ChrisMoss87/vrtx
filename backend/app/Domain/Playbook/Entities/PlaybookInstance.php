<?php

declare(strict_types=1);

namespace App\Domain\Playbook\Entities;

use App\Domain\Playbook\ValueObjects\InstanceStatus;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;
use InvalidArgumentException;

final class PlaybookInstance implements Entity
{
    private function __construct(
        private ?int $id,
        private int $playbookId,
        private string $relatedModule,
        private int $relatedId,
        private InstanceStatus $status,
        private ?DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $targetCompletionAt,
        private ?DateTimeImmutable $completedAt,
        private ?DateTimeImmutable $pausedAt,
        private ?int $ownerId,
        private int $progressPercent,
        private ?array $metadata,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    public static function create(
        int $playbookId,
        string $relatedModule,
        int $relatedId,
        ?int $ownerId = null,
        ?DateTimeImmutable $targetCompletionAt = null,
    ): self {
        return new self(
            id: null,
            playbookId: $playbookId,
            relatedModule: $relatedModule,
            relatedId: $relatedId,
            status: InstanceStatus::PENDING,
            startedAt: null,
            targetCompletionAt: $targetCompletionAt,
            completedAt: null,
            pausedAt: null,
            ownerId: $ownerId,
            progressPercent: 0,
            metadata: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $playbookId,
        string $relatedModule,
        int $relatedId,
        InstanceStatus $status,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $targetCompletionAt,
        ?DateTimeImmutable $completedAt,
        ?DateTimeImmutable $pausedAt,
        ?int $ownerId,
        int $progressPercent,
        ?array $metadata,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            id: $id,
            playbookId: $playbookId,
            relatedModule: $relatedModule,
            relatedId: $relatedId,
            status: $status,
            startedAt: $startedAt,
            targetCompletionAt: $targetCompletionAt,
            completedAt: $completedAt,
            pausedAt: $pausedAt,
            ownerId: $ownerId,
            progressPercent: $progressPercent,
            metadata: $metadata,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    public function start(): self
    {
        if (!$this->status->canTransitionTo(InstanceStatus::ACTIVE)) {
            throw new InvalidArgumentException(
                "Cannot start instance in {$this->status->value} status"
            );
        }

        $clone = clone $this;
        $clone->status = InstanceStatus::ACTIVE;
        $clone->startedAt = $clone->startedAt ?? new DateTimeImmutable();
        $clone->pausedAt = null;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function pause(): self
    {
        if (!$this->status->canTransitionTo(InstanceStatus::PAUSED)) {
            throw new InvalidArgumentException(
                "Cannot pause instance in {$this->status->value} status"
            );
        }

        $clone = clone $this;
        $clone->status = InstanceStatus::PAUSED;
        $clone->pausedAt = new DateTimeImmutable();
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function resume(): self
    {
        if (!$this->status->canTransitionTo(InstanceStatus::ACTIVE)) {
            throw new InvalidArgumentException(
                "Cannot resume instance in {$this->status->value} status"
            );
        }

        $clone = clone $this;
        $clone->status = InstanceStatus::ACTIVE;
        $clone->pausedAt = null;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function complete(): self
    {
        if (!$this->status->canTransitionTo(InstanceStatus::COMPLETED)) {
            throw new InvalidArgumentException(
                "Cannot complete instance in {$this->status->value} status"
            );
        }

        $clone = clone $this;
        $clone->status = InstanceStatus::COMPLETED;
        $clone->completedAt = new DateTimeImmutable();
        $clone->progressPercent = 100;
        $clone->pausedAt = null;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function cancel(): self
    {
        if (!$this->status->canTransitionTo(InstanceStatus::CANCELLED)) {
            throw new InvalidArgumentException(
                "Cannot cancel instance in {$this->status->value} status"
            );
        }

        $clone = clone $this;
        $clone->status = InstanceStatus::CANCELLED;
        $clone->pausedAt = null;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateProgress(int $percent): self
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('Progress must be between 0 and 100');
        }

        if ($this->status->isTerminal()) {
            return $this;
        }

        $clone = clone $this;
        $clone->progressPercent = $percent;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateTargetCompletion(?DateTimeImmutable $targetCompletionAt): self
    {
        if ($this->status->isTerminal()) {
            return $this;
        }

        $clone = clone $this;
        $clone->targetCompletionAt = $targetCompletionAt;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function assignOwner(?int $ownerId): self
    {
        $clone = clone $this;
        $clone->ownerId = $ownerId;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateMetadata(array $metadata): self
    {
        $clone = clone $this;
        $clone->metadata = $metadata;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function setMetadataValue(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->metadata = $clone->metadata ?? [];
        $clone->metadata[$key] = $value;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function removeMetadataValue(string $key): self
    {
        if (!isset($this->metadata[$key])) {
            return $this;
        }

        $clone = clone $this;
        unset($clone->metadata[$key]);
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function delete(): self
    {
        if ($this->deletedAt !== null) {
            return $this;
        }

        $clone = clone $this;
        $clone->deletedAt = new DateTimeImmutable();
        return $clone;
    }

    public function restore(): self
    {
        if ($this->deletedAt === null) {
            return $this;
        }

        $clone = clone $this;
        $clone->deletedAt = null;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function isActive(): bool
    {
        return $this->status === InstanceStatus::ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === InstanceStatus::PAUSED;
    }

    public function isCompleted(): bool
    {
        return $this->status === InstanceStatus::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === InstanceStatus::CANCELLED;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function isOverdue(): bool
    {
        if ($this->targetCompletionAt === null || $this->status->isTerminal()) {
            return false;
        }

        return $this->targetCompletionAt < new DateTimeImmutable();
    }

    public function hasOwner(): bool
    {
        return $this->ownerId !== null;
    }

    public function hasMetadata(): bool
    {
        return $this->metadata !== null && !empty($this->metadata);
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function getDurationInDays(): ?int
    {
        if ($this->startedAt === null) {
            return null;
        }

        $endDate = $this->completedAt ?? new DateTimeImmutable();
        return $this->startedAt->diff($endDate)->days;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlaybookId(): int
    {
        return $this->playbookId;
    }

    public function getRelatedModule(): string
    {
        return $this->relatedModule;
    }

    public function getRelatedId(): int
    {
        return $this->relatedId;
    }

    public function getStatus(): InstanceStatus
    {
        return $this->status;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getTargetCompletionAt(): ?DateTimeImmutable
    {
        return $this->targetCompletionAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getPausedAt(): ?DateTimeImmutable
    {
        return $this->pausedAt;
    }

    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }

    public function getProgressPercent(): int
    {
        return $this->progressPercent;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function equals(Entity $other): bool
    {
        return $other instanceof self
            && $this->id !== null
            && $this->id === $other->id;
    }
}
