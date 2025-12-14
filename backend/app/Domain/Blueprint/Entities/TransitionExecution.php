<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Entities;

use App\Domain\Blueprint\ValueObjects\ExecutionStatus;
use App\Domain\Shared\ValueObjects\UserId;

/**
 * Represents an execution of a blueprint transition.
 */
class TransitionExecution
{
    private ?int $id = null;
    private int $transitionId;
    private int $recordId;
    private int $fromStateId;
    private int $toStateId;
    private ExecutionStatus $status;
    private ?UserId $executedBy;
    private array $requirementData;
    private array $actionResults;
    private ?string $errorMessage;
    private ?\DateTimeImmutable $startedAt;
    private ?\DateTimeImmutable $completedAt;
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $updatedAt = null;

    private function __construct(
        int $transitionId,
        int $recordId,
        int $fromStateId,
        int $toStateId,
        ?UserId $executedBy = null,
    ) {
        $this->transitionId = $transitionId;
        $this->recordId = $recordId;
        $this->fromStateId = $fromStateId;
        $this->toStateId = $toStateId;
        $this->executedBy = $executedBy;
        $this->status = ExecutionStatus::PENDING;
        $this->requirementData = [];
        $this->actionResults = [];
        $this->errorMessage = null;
        $this->startedAt = null;
        $this->completedAt = null;
    }

    public static function create(
        int $transitionId,
        int $recordId,
        int $fromStateId,
        int $toStateId,
        ?UserId $executedBy = null,
    ): self {
        return new self(
            transitionId: $transitionId,
            recordId: $recordId,
            fromStateId: $fromStateId,
            toStateId: $toStateId,
            executedBy: $executedBy,
        );
    }

    public static function reconstitute(
        int $id,
        int $transitionId,
        int $recordId,
        int $fromStateId,
        int $toStateId,
        ExecutionStatus $status,
        ?UserId $executedBy,
        array $requirementData,
        array $actionResults,
        ?string $errorMessage,
        ?\DateTimeImmutable $startedAt,
        ?\DateTimeImmutable $completedAt,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $execution = new self(
            transitionId: $transitionId,
            recordId: $recordId,
            fromStateId: $fromStateId,
            toStateId: $toStateId,
            executedBy: $executedBy,
        );
        $execution->id = $id;
        $execution->status = $status;
        $execution->requirementData = $requirementData;
        $execution->actionResults = $actionResults;
        $execution->errorMessage = $errorMessage;
        $execution->startedAt = $startedAt;
        $execution->completedAt = $completedAt;
        $execution->createdAt = $createdAt;
        $execution->updatedAt = $updatedAt;

        return $execution;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransitionId(): int
    {
        return $this->transitionId;
    }

    public function getRecordId(): int
    {
        return $this->recordId;
    }

    public function getFromStateId(): int
    {
        return $this->fromStateId;
    }

    public function getToStateId(): int
    {
        return $this->toStateId;
    }

    public function getStatus(): ExecutionStatus
    {
        return $this->status;
    }

    public function getExecutedBy(): ?UserId
    {
        return $this->executedBy;
    }

    public function getRequirementData(): array
    {
        return $this->requirementData;
    }

    public function getActionResults(): array
    {
        return $this->actionResults;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Domain methods
    public function start(): void
    {
        $this->status = ExecutionStatus::IN_PROGRESS;
        $this->startedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function awaitApproval(): void
    {
        $this->status = ExecutionStatus::AWAITING_APPROVAL;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function awaitRequirements(): void
    {
        $this->status = ExecutionStatus::AWAITING_REQUIREMENTS;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function complete(): void
    {
        $this->status = ExecutionStatus::COMPLETED;
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function fail(string $errorMessage): void
    {
        $this->status = ExecutionStatus::FAILED;
        $this->errorMessage = $errorMessage;
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        $this->status = ExecutionStatus::CANCELLED;
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function rollback(): void
    {
        $this->status = ExecutionStatus::ROLLED_BACK;
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setRequirementData(array $data): void
    {
        $this->requirementData = $data;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function addRequirementData(string $key, mixed $value): void
    {
        $this->requirementData[$key] = $value;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function addActionResult(string $actionKey, array $result): void
    {
        $this->actionResults[$actionKey] = $result;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getDurationMs(): ?int
    {
        if ($this->startedAt === null || $this->completedAt === null) {
            return null;
        }

        return (int) (($this->completedAt->getTimestamp() - $this->startedAt->getTimestamp()) * 1000);
    }
}
