<?php

declare(strict_types=1);

namespace App\Domain\Cadence\Entities;

use App\Domain\Cadence\ValueObjects\EnrollmentStatus;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class CadenceEnrollment implements Entity
{
    private function __construct(
        private ?int $id,
        private int $cadenceId,
        private int $recordId,
        private ?int $currentStepId,
        private EnrollmentStatus $status,
        private DateTimeImmutable $enrolledAt,
        private ?DateTimeImmutable $nextStepAt,
        private ?DateTimeImmutable $completedAt,
        private ?DateTimeImmutable $pausedAt,
        private ?string $exitReason,
        private int $enrolledBy,
        private array $metadata,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $cadenceId,
        int $recordId,
        int $enrolledBy,
        ?DateTimeImmutable $nextStepAt = null,
    ): self {
        return new self(
            id: null,
            cadenceId: $cadenceId,
            recordId: $recordId,
            currentStepId: null,
            status: EnrollmentStatus::ACTIVE,
            enrolledAt: new DateTimeImmutable(),
            nextStepAt: $nextStepAt ?? new DateTimeImmutable(),
            completedAt: null,
            pausedAt: null,
            exitReason: null,
            enrolledBy: $enrolledBy,
            metadata: [],
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $cadenceId,
        int $recordId,
        ?int $currentStepId,
        EnrollmentStatus $status,
        DateTimeImmutable $enrolledAt,
        ?DateTimeImmutable $nextStepAt,
        ?DateTimeImmutable $completedAt,
        ?DateTimeImmutable $pausedAt,
        ?string $exitReason,
        int $enrolledBy,
        array $metadata,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            cadenceId: $cadenceId,
            recordId: $recordId,
            currentStepId: $currentStepId,
            status: $status,
            enrolledAt: $enrolledAt,
            nextStepAt: $nextStepAt,
            completedAt: $completedAt,
            pausedAt: $pausedAt,
            exitReason: $exitReason,
            enrolledBy: $enrolledBy,
            metadata: $metadata,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getCadenceId(): int { return $this->cadenceId; }
    public function getRecordId(): int { return $this->recordId; }
    public function getCurrentStepId(): ?int { return $this->currentStepId; }
    public function getStatus(): EnrollmentStatus { return $this->status; }
    public function getEnrolledAt(): DateTimeImmutable { return $this->enrolledAt; }
    public function getNextStepAt(): ?DateTimeImmutable { return $this->nextStepAt; }
    public function getCompletedAt(): ?DateTimeImmutable { return $this->completedAt; }
    public function getPausedAt(): ?DateTimeImmutable { return $this->pausedAt; }
    public function getExitReason(): ?string { return $this->exitReason; }
    public function getEnrolledBy(): int { return $this->enrolledBy; }
    public function getMetadata(): array { return $this->metadata; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }

    // Business logic methods
    public function advanceToStep(int $stepId, DateTimeImmutable $nextStepAt): self
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException("Cannot advance enrollment in {$this->status->value} status");
        }

        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            recordId: $this->recordId,
            currentStepId: $stepId,
            status: $this->status,
            enrolledAt: $this->enrolledAt,
            nextStepAt: $nextStepAt,
            completedAt: $this->completedAt,
            pausedAt: $this->pausedAt,
            exitReason: $this->exitReason,
            enrolledBy: $this->enrolledBy,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function pause(?string $reason = null): self
    {
        if (!$this->status->canPause()) {
            throw new \DomainException("Cannot pause enrollment in {$this->status->value} status");
        }

        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            recordId: $this->recordId,
            currentStepId: $this->currentStepId,
            status: EnrollmentStatus::PAUSED,
            enrolledAt: $this->enrolledAt,
            nextStepAt: $this->nextStepAt,
            completedAt: $this->completedAt,
            pausedAt: new DateTimeImmutable(),
            exitReason: $reason,
            enrolledBy: $this->enrolledBy,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function resume(): self
    {
        if (!$this->status->canResume()) {
            throw new \DomainException("Cannot resume enrollment in {$this->status->value} status");
        }

        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            recordId: $this->recordId,
            currentStepId: $this->currentStepId,
            status: EnrollmentStatus::ACTIVE,
            enrolledAt: $this->enrolledAt,
            nextStepAt: $this->nextStepAt,
            completedAt: $this->completedAt,
            pausedAt: null,
            exitReason: null,
            enrolledBy: $this->enrolledBy,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function complete(?string $reason = null): self
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException("Enrollment already in terminal status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            recordId: $this->recordId,
            currentStepId: $this->currentStepId,
            status: EnrollmentStatus::COMPLETED,
            enrolledAt: $this->enrolledAt,
            nextStepAt: null,
            completedAt: new DateTimeImmutable(),
            pausedAt: $this->pausedAt,
            exitReason: $reason ?? 'Sequence completed',
            enrolledBy: $this->enrolledBy,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function exitWithStatus(EnrollmentStatus $status, string $reason): self
    {
        if (!$status->isTerminal()) {
            throw new \DomainException("Can only exit to terminal status, got: {$status->value}");
        }

        if ($this->status->isTerminal()) {
            throw new \DomainException("Enrollment already in terminal status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            recordId: $this->recordId,
            currentStepId: $this->currentStepId,
            status: $status,
            enrolledAt: $this->enrolledAt,
            nextStepAt: null,
            completedAt: new DateTimeImmutable(),
            pausedAt: $this->pausedAt,
            exitReason: $reason,
            enrolledBy: $this->enrolledBy,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function markAsReplied(string $reason = 'Recipient replied'): self
    {
        return $this->exitWithStatus(EnrollmentStatus::REPLIED, $reason);
    }

    public function markAsBounced(string $reason = 'Email bounced'): self
    {
        return $this->exitWithStatus(EnrollmentStatus::BOUNCED, $reason);
    }

    public function markAsUnsubscribed(string $reason = 'Recipient unsubscribed'): self
    {
        return $this->exitWithStatus(EnrollmentStatus::UNSUBSCRIBED, $reason);
    }

    public function markAsMeetingBooked(string $reason = 'Meeting booked'): self
    {
        return $this->exitWithStatus(EnrollmentStatus::MEETING_BOOKED, $reason);
    }

    public function manuallyRemove(string $reason): self
    {
        return $this->exitWithStatus(EnrollmentStatus::MANUALLY_REMOVED, $reason);
    }

    public function updateMetadata(array $metadata): self
    {
        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            recordId: $this->recordId,
            currentStepId: $this->currentStepId,
            status: $this->status,
            enrolledAt: $this->enrolledAt,
            nextStepAt: $this->nextStepAt,
            completedAt: $this->completedAt,
            pausedAt: $this->pausedAt,
            exitReason: $this->exitReason,
            enrolledBy: $this->enrolledBy,
            metadata: $metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function addMetadata(string $key, mixed $value): self
    {
        $metadata = $this->metadata;
        $metadata[$key] = $value;

        return $this->updateMetadata($metadata);
    }

    public function scheduleNextStep(DateTimeImmutable $nextStepAt): self
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException("Cannot schedule next step for enrollment in {$this->status->value} status");
        }

        return new self(
            id: $this->id,
            cadenceId: $this->cadenceId,
            recordId: $this->recordId,
            currentStepId: $this->currentStepId,
            status: $this->status,
            enrolledAt: $this->enrolledAt,
            nextStepAt: $nextStepAt,
            completedAt: $this->completedAt,
            pausedAt: $this->pausedAt,
            exitReason: $this->exitReason,
            enrolledBy: $this->enrolledBy,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    // Query methods
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isDue(?DateTimeImmutable $asOf = null): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->nextStepAt === null) {
            return false;
        }

        $checkTime = $asOf ?? new DateTimeImmutable();
        return $this->nextStepAt <= $checkTime;
    }

    public function isPaused(): bool
    {
        return $this->status === EnrollmentStatus::PAUSED;
    }

    public function isCompleted(): bool
    {
        return $this->status->isTerminal();
    }

    public function wasSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    public function getDurationInDays(): int
    {
        $endDate = $this->completedAt ?? new DateTimeImmutable();
        return $this->enrolledAt->diff($endDate)->days;
    }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        if ($this->id === null || $other->getId() === null) {
            return false;
        }

        return $this->id === $other->getId();
    }
}
