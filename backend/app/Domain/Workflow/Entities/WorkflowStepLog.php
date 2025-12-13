<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Entities;

use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Workflow\ValueObjects\ActionType;

/**
 * WorkflowStepLog entity.
 *
 * Represents a log entry for a single step execution within a workflow execution.
 */
final class WorkflowStepLog implements Entity
{
    private function __construct(
        private ?int $id,
        private int $executionId,
        private int $stepId,
        private ActionType $actionType,
        private string $status, // 'pending', 'running', 'completed', 'failed', 'skipped'
        private ?Timestamp $startedAt,
        private ?Timestamp $completedAt,
        private ?int $durationMs,
        private array $inputData,
        private array $outputData,
        private ?string $errorMessage,
        private int $attemptNumber,
        private ?Timestamp $createdAt,
    ) {}

    /**
     * Create a new step log entry.
     */
    public static function create(
        int $executionId,
        int $stepId,
        ActionType $actionType,
        array $inputData = [],
        int $attemptNumber = 1,
    ): self {
        return new self(
            id: null,
            executionId: $executionId,
            stepId: $stepId,
            actionType: $actionType,
            status: 'pending',
            startedAt: null,
            completedAt: null,
            durationMs: null,
            inputData: $inputData,
            outputData: [],
            errorMessage: null,
            attemptNumber: $attemptNumber,
            createdAt: Timestamp::now(),
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        int $executionId,
        int $stepId,
        ActionType $actionType,
        string $status,
        ?Timestamp $startedAt,
        ?Timestamp $completedAt,
        ?int $durationMs,
        array $inputData,
        array $outputData,
        ?string $errorMessage,
        int $attemptNumber,
        ?Timestamp $createdAt,
    ): self {
        return new self(
            id: $id,
            executionId: $executionId,
            stepId: $stepId,
            actionType: $actionType,
            status: $status,
            startedAt: $startedAt,
            completedAt: $completedAt,
            durationMs: $durationMs,
            inputData: $inputData,
            outputData: $outputData,
            errorMessage: $errorMessage,
            attemptNumber: $attemptNumber,
            createdAt: $createdAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Mark as started.
     */
    public function markAsStarted(): void
    {
        $this->status = 'running';
        $this->startedAt = Timestamp::now();
    }

    /**
     * Mark as completed.
     */
    public function markAsCompleted(array $outputData = []): void
    {
        $this->status = 'completed';
        $this->completedAt = Timestamp::now();
        $this->outputData = $outputData;
        $this->calculateDuration();
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $errorMessage, array $outputData = []): void
    {
        $this->status = 'failed';
        $this->completedAt = Timestamp::now();
        $this->errorMessage = $errorMessage;
        $this->outputData = $outputData;
        $this->calculateDuration();
    }

    /**
     * Mark as skipped.
     */
    public function markAsSkipped(string $reason = ''): void
    {
        $this->status = 'skipped';
        $this->completedAt = Timestamp::now();
        if ($reason) {
            $this->errorMessage = $reason;
        }
    }

    /**
     * Calculate duration from start to completion.
     */
    private function calculateDuration(): void
    {
        if ($this->startedAt !== null && $this->completedAt !== null) {
            $this->durationMs = $this->completedAt->diffInSeconds($this->startedAt) * 1000;
        }
    }

    // ========== Entity Implementation ==========

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

    public function executionId(): int
    {
        return $this->executionId;
    }

    public function stepId(): int
    {
        return $this->stepId;
    }

    public function actionType(): ActionType
    {
        return $this->actionType;
    }

    public function status(): string
    {
        return $this->status;
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
    public function inputData(): array
    {
        return $this->inputData;
    }

    /**
     * @return array<string, mixed>
     */
    public function outputData(): array
    {
        return $this->outputData;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function attemptNumber(): int
    {
        return $this->attemptNumber;
    }

    public function createdAt(): ?Timestamp
    {
        return $this->createdAt;
    }

    /**
     * Check if this log represents a successful execution.
     */
    public function isSuccess(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if this log represents a failure.
     */
    public function isFailure(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if this step was skipped.
     */
    public function wasSkipped(): bool
    {
        return $this->status === 'skipped';
    }
}
