<?php

declare(strict_types=1);

namespace App\Domain\Cadence\Entities;

use App\Domain\Cadence\ValueObjects\ExecutionResult;
use App\Domain\Cadence\ValueObjects\ExecutionStatus;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class CadenceStepExecution implements Entity
{
    private function __construct(
        private ?int $id,
        private int $enrollmentId,
        private int $stepId,
        private DateTimeImmutable $scheduledAt,
        private ?DateTimeImmutable $executedAt,
        private ExecutionStatus $status,
        private ?ExecutionResult $result,
        private ?string $errorMessage,
        private array $metadata,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $enrollmentId,
        int $stepId,
        DateTimeImmutable $scheduledAt,
    ): self {
        return new self(
            id: null,
            enrollmentId: $enrollmentId,
            stepId: $stepId,
            scheduledAt: $scheduledAt,
            executedAt: null,
            status: ExecutionStatus::SCHEDULED,
            result: null,
            errorMessage: null,
            metadata: [],
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $enrollmentId,
        int $stepId,
        DateTimeImmutable $scheduledAt,
        ?DateTimeImmutable $executedAt,
        ExecutionStatus $status,
        ?ExecutionResult $result,
        ?string $errorMessage,
        array $metadata,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            enrollmentId: $enrollmentId,
            stepId: $stepId,
            scheduledAt: $scheduledAt,
            executedAt: $executedAt,
            status: $status,
            result: $result,
            errorMessage: $errorMessage,
            metadata: $metadata,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getEnrollmentId(): int { return $this->enrollmentId; }
    public function getStepId(): int { return $this->stepId; }
    public function getScheduledAt(): DateTimeImmutable { return $this->scheduledAt; }
    public function getExecutedAt(): ?DateTimeImmutable { return $this->executedAt; }
    public function getStatus(): ExecutionStatus { return $this->status; }
    public function getResult(): ?ExecutionResult { return $this->result; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function getMetadata(): array { return $this->metadata; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }

    // Business logic methods
    public function markAsExecuting(): self
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException("Cannot mark execution as executing when in terminal status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            enrollmentId: $this->enrollmentId,
            stepId: $this->stepId,
            scheduledAt: $this->scheduledAt,
            executedAt: $this->executedAt,
            status: ExecutionStatus::EXECUTING,
            result: $this->result,
            errorMessage: $this->errorMessage,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function markAsCompleted(ExecutionResult $result, array $additionalMetadata = []): self
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException("Cannot mark execution as completed when in terminal status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            enrollmentId: $this->enrollmentId,
            stepId: $this->stepId,
            scheduledAt: $this->scheduledAt,
            executedAt: new DateTimeImmutable(),
            status: ExecutionStatus::COMPLETED,
            result: $result,
            errorMessage: null,
            metadata: array_merge($this->metadata, $additionalMetadata),
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function markAsFailed(string $errorMessage, array $additionalMetadata = []): self
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException("Cannot mark execution as failed when in terminal status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            enrollmentId: $this->enrollmentId,
            stepId: $this->stepId,
            scheduledAt: $this->scheduledAt,
            executedAt: new DateTimeImmutable(),
            status: ExecutionStatus::FAILED,
            result: ExecutionResult::FAILED,
            errorMessage: $errorMessage,
            metadata: array_merge($this->metadata, $additionalMetadata),
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function markAsSkipped(string $reason): self
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException("Cannot mark execution as skipped when in terminal status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            enrollmentId: $this->enrollmentId,
            stepId: $this->stepId,
            scheduledAt: $this->scheduledAt,
            executedAt: new DateTimeImmutable(),
            status: ExecutionStatus::SKIPPED,
            result: ExecutionResult::SKIPPED,
            errorMessage: $reason,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function cancel(string $reason = 'Execution cancelled'): self
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException("Cannot cancel execution when in terminal status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            enrollmentId: $this->enrollmentId,
            stepId: $this->stepId,
            scheduledAt: $this->scheduledAt,
            executedAt: null,
            status: ExecutionStatus::CANCELLED,
            result: null,
            errorMessage: $reason,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function reschedule(DateTimeImmutable $newScheduledAt): self
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException("Cannot reschedule execution when in terminal status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            enrollmentId: $this->enrollmentId,
            stepId: $this->stepId,
            scheduledAt: $newScheduledAt,
            executedAt: $this->executedAt,
            status: ExecutionStatus::SCHEDULED,
            result: $this->result,
            errorMessage: $this->errorMessage,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function updateMetadata(array $metadata): self
    {
        return new self(
            id: $this->id,
            enrollmentId: $this->enrollmentId,
            stepId: $this->stepId,
            scheduledAt: $this->scheduledAt,
            executedAt: $this->executedAt,
            status: $this->status,
            result: $this->result,
            errorMessage: $this->errorMessage,
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

    // Email-specific result tracking
    public function markAsSent(array $metadata = []): self
    {
        return $this->markAsCompleted(ExecutionResult::SENT, $metadata);
    }

    public function markAsDelivered(array $metadata = []): self
    {
        return $this->markAsCompleted(ExecutionResult::DELIVERED, $metadata);
    }

    public function markAsOpened(array $metadata = []): self
    {
        return $this->markAsCompleted(ExecutionResult::OPENED, $metadata);
    }

    public function markAsClicked(array $metadata = []): self
    {
        return $this->markAsCompleted(ExecutionResult::CLICKED, $metadata);
    }

    public function markAsReplied(array $metadata = []): self
    {
        return $this->markAsCompleted(ExecutionResult::REPLIED, $metadata);
    }

    public function markAsBounced(string $reason, array $metadata = []): self
    {
        $metadata['bounce_reason'] = $reason;
        return $this->markAsFailed($reason, $metadata);
    }

    // Query methods
    public function isDue(?DateTimeImmutable $asOf = null): bool
    {
        if (!$this->status->isDue()) {
            return false;
        }

        $checkTime = $asOf ?? new DateTimeImmutable();
        return $this->scheduledAt <= $checkTime;
    }

    public function isScheduled(): bool
    {
        return $this->status === ExecutionStatus::SCHEDULED;
    }

    public function isExecuting(): bool
    {
        return $this->status === ExecutionStatus::EXECUTING;
    }

    public function isCompleted(): bool
    {
        return $this->status === ExecutionStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === ExecutionStatus::FAILED;
    }

    public function isSkipped(): bool
    {
        return $this->status === ExecutionStatus::SKIPPED;
    }

    public function isCancelled(): bool
    {
        return $this->status === ExecutionStatus::CANCELLED;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function wasSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    public function hasEngagement(): bool
    {
        return $this->result !== null && $this->result->isEngagement();
    }

    public function getExecutionDuration(): ?int
    {
        if ($this->executedAt === null) {
            return null;
        }

        return $this->executedAt->getTimestamp() - $this->scheduledAt->getTimestamp();
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
