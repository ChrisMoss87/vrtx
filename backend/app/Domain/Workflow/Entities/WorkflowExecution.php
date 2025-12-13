<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Entities;

use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Events\DomainEvent;
use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Workflow\ValueObjects\ExecutionStatus;

/**
 * WorkflowExecution aggregate root entity.
 *
 * Represents a single execution instance of a workflow.
 */
final class WorkflowExecution implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private int $workflowId,
        private string $triggerType,
        private ?int $triggerRecordId,
        private ?string $triggerRecordType,
        private ExecutionStatus $status,
        private ?Timestamp $queuedAt,
        private ?Timestamp $startedAt,
        private ?Timestamp $completedAt,
        private ?int $durationMs,
        private array $contextData,
        private int $stepsCompleted,
        private int $stepsFailed,
        private int $stepsSkipped,
        private ?string $errorMessage,
        private ?UserId $triggeredBy,
        private ?Timestamp $createdAt,
    ) {}

    /**
     * Create a new execution.
     */
    public static function create(
        int $workflowId,
        string $triggerType,
        ?int $triggerRecordId = null,
        ?string $triggerRecordType = null,
        array $contextData = [],
        ?UserId $triggeredBy = null,
    ): self {
        return new self(
            id: null,
            workflowId: $workflowId,
            triggerType: $triggerType,
            triggerRecordId: $triggerRecordId,
            triggerRecordType: $triggerRecordType,
            status: ExecutionStatus::PENDING,
            queuedAt: null,
            startedAt: null,
            completedAt: null,
            durationMs: null,
            contextData: $contextData,
            stepsCompleted: 0,
            stepsFailed: 0,
            stepsSkipped: 0,
            errorMessage: null,
            triggeredBy: $triggeredBy,
            createdAt: Timestamp::now(),
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        int $workflowId,
        string $triggerType,
        ?int $triggerRecordId,
        ?string $triggerRecordType,
        ExecutionStatus $status,
        ?Timestamp $queuedAt,
        ?Timestamp $startedAt,
        ?Timestamp $completedAt,
        ?int $durationMs,
        array $contextData,
        int $stepsCompleted,
        int $stepsFailed,
        int $stepsSkipped,
        ?string $errorMessage,
        ?UserId $triggeredBy,
        ?Timestamp $createdAt,
    ): self {
        return new self(
            id: $id,
            workflowId: $workflowId,
            triggerType: $triggerType,
            triggerRecordId: $triggerRecordId,
            triggerRecordType: $triggerRecordType,
            status: $status,
            queuedAt: $queuedAt,
            startedAt: $startedAt,
            completedAt: $completedAt,
            durationMs: $durationMs,
            contextData: $contextData,
            stepsCompleted: $stepsCompleted,
            stepsFailed: $stepsFailed,
            stepsSkipped: $stepsSkipped,
            errorMessage: $errorMessage,
            triggeredBy: $triggeredBy,
            createdAt: $createdAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Mark execution as queued.
     */
    public function markAsQueued(): void
    {
        if (!$this->status->canTransitionTo(ExecutionStatus::QUEUED)) {
            throw new \DomainException(
                "Cannot transition from {$this->status->value} to queued"
            );
        }

        $this->status = ExecutionStatus::QUEUED;
        $this->queuedAt = Timestamp::now();
    }

    /**
     * Mark execution as started.
     */
    public function markAsStarted(): void
    {
        if (!$this->status->canTransitionTo(ExecutionStatus::RUNNING)) {
            throw new \DomainException(
                "Cannot transition from {$this->status->value} to running"
            );
        }

        $this->status = ExecutionStatus::RUNNING;
        $this->startedAt = Timestamp::now();
    }

    /**
     * Mark execution as completed.
     */
    public function markAsCompleted(): void
    {
        if (!$this->status->canTransitionTo(ExecutionStatus::COMPLETED)) {
            throw new \DomainException(
                "Cannot transition from {$this->status->value} to completed"
            );
        }

        $this->status = ExecutionStatus::COMPLETED;
        $this->completedAt = Timestamp::now();
        $this->calculateDuration();
    }

    /**
     * Mark execution as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        if (!$this->status->canTransitionTo(ExecutionStatus::FAILED)) {
            throw new \DomainException(
                "Cannot transition from {$this->status->value} to failed"
            );
        }

        $this->status = ExecutionStatus::FAILED;
        $this->completedAt = Timestamp::now();
        $this->errorMessage = $errorMessage;
        $this->calculateDuration();
    }

    /**
     * Mark execution as cancelled.
     */
    public function markAsCancelled(): void
    {
        if (!$this->status->canTransitionTo(ExecutionStatus::CANCELLED)) {
            throw new \DomainException(
                "Cannot transition from {$this->status->value} to cancelled"
            );
        }

        $this->status = ExecutionStatus::CANCELLED;
        $this->completedAt = Timestamp::now();
    }

    /**
     * Record a step completion.
     */
    public function incrementStepsCompleted(): void
    {
        $this->stepsCompleted++;
    }

    /**
     * Record a step failure.
     */
    public function incrementStepsFailed(): void
    {
        $this->stepsFailed++;
    }

    /**
     * Record a step skip.
     */
    public function incrementStepsSkipped(): void
    {
        $this->stepsSkipped++;
    }

    /**
     * Update the context data.
     */
    public function updateContextData(array $data): void
    {
        $this->contextData = array_merge($this->contextData, $data);
    }

    /**
     * Calculate and set the duration.
     */
    private function calculateDuration(): void
    {
        if ($this->startedAt !== null && $this->completedAt !== null) {
            $this->durationMs = $this->completedAt->diffInSeconds($this->startedAt) * 1000;
        }
    }

    // ========== AggregateRoot Implementation ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->id !== null && $this->id === $other->id;
    }

    // ========== Getters ==========

    public function workflowId(): int
    {
        return $this->workflowId;
    }

    public function triggerType(): string
    {
        return $this->triggerType;
    }

    public function triggerRecordId(): ?int
    {
        return $this->triggerRecordId;
    }

    public function triggerRecordType(): ?string
    {
        return $this->triggerRecordType;
    }

    public function status(): ExecutionStatus
    {
        return $this->status;
    }

    public function queuedAt(): ?Timestamp
    {
        return $this->queuedAt;
    }

    public function startedAt(): ?Timestamp
    {
        return $this->startedAt;
    }

    public function completedAt(): ?Timestamp
    {
        return $this->completedAt;
    }

    public function durationMs(): ?int
    {
        return $this->durationMs;
    }

    /**
     * @return array<string, mixed>
     */
    public function contextData(): array
    {
        return $this->contextData;
    }

    public function stepsCompleted(): int
    {
        return $this->stepsCompleted;
    }

    public function stepsFailed(): int
    {
        return $this->stepsFailed;
    }

    public function stepsSkipped(): int
    {
        return $this->stepsSkipped;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function triggeredBy(): ?UserId
    {
        return $this->triggeredBy;
    }

    public function createdAt(): ?Timestamp
    {
        return $this->createdAt;
    }

    /**
     * Check if execution is still in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status->isInProgress();
    }

    /**
     * Check if execution has finished.
     */
    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    /**
     * Get total steps processed.
     */
    public function totalStepsProcessed(): int
    {
        return $this->stepsCompleted + $this->stepsFailed + $this->stepsSkipped;
    }
}
