<?php

declare(strict_types=1);

namespace App\Domain\Playbook\Entities;

use App\Domain\Playbook\ValueObjects\TaskInstanceStatus;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;
use InvalidArgumentException;

final class PlaybookTaskInstance implements Entity
{
    private function __construct(
        private ?int $id,
        private int $instanceId,
        private int $taskId,
        private TaskInstanceStatus $status,
        private ?DateTimeImmutable $dueAt,
        private ?DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $completedAt,
        private ?int $assignedTo,
        private ?int $completedBy,
        private ?string $notes,
        private ?array $checklistStatus,
        private ?int $timeSpent,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $instanceId,
        int $taskId,
        ?DateTimeImmutable $dueAt = null,
        ?int $assignedTo = null,
    ): self {
        return new self(
            id: null,
            instanceId: $instanceId,
            taskId: $taskId,
            status: TaskInstanceStatus::PENDING,
            dueAt: $dueAt,
            startedAt: null,
            completedAt: null,
            assignedTo: $assignedTo,
            completedBy: null,
            notes: null,
            checklistStatus: null,
            timeSpent: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $instanceId,
        int $taskId,
        TaskInstanceStatus $status,
        ?DateTimeImmutable $dueAt,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $completedAt,
        ?int $assignedTo,
        ?int $completedBy,
        ?string $notes,
        ?array $checklistStatus,
        ?int $timeSpent,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            instanceId: $instanceId,
            taskId: $taskId,
            status: $status,
            dueAt: $dueAt,
            startedAt: $startedAt,
            completedAt: $completedAt,
            assignedTo: $assignedTo,
            completedBy: $completedBy,
            notes: $notes,
            checklistStatus: $checklistStatus,
            timeSpent: $timeSpent,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function start(): self
    {
        if (!$this->status->canTransitionTo(TaskInstanceStatus::IN_PROGRESS)) {
            throw new InvalidArgumentException(
                "Cannot start task in {$this->status->value} status"
            );
        }

        $clone = clone $this;
        $clone->status = TaskInstanceStatus::IN_PROGRESS;
        $clone->startedAt = new DateTimeImmutable();
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function complete(?int $completedBy = null, ?string $notes = null, ?int $timeSpent = null): self
    {
        if (!$this->status->canTransitionTo(TaskInstanceStatus::COMPLETED)) {
            throw new InvalidArgumentException(
                "Cannot complete task in {$this->status->value} status"
            );
        }

        $clone = clone $this;
        $clone->status = TaskInstanceStatus::COMPLETED;
        $clone->completedAt = new DateTimeImmutable();
        $clone->completedBy = $completedBy;

        if ($notes !== null) {
            $clone->notes = $notes;
        }

        if ($timeSpent !== null) {
            $clone->timeSpent = $timeSpent;
        }

        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function skip(?string $reason = null): self
    {
        if (!$this->status->canTransitionTo(TaskInstanceStatus::SKIPPED)) {
            throw new InvalidArgumentException(
                "Cannot skip task in {$this->status->value} status"
            );
        }

        $clone = clone $this;
        $clone->status = TaskInstanceStatus::SKIPPED;
        $clone->completedAt = new DateTimeImmutable();

        if ($reason !== null) {
            $clone->notes = $reason;
        }

        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function block(?string $reason = null): self
    {
        if (!$this->status->canTransitionTo(TaskInstanceStatus::BLOCKED)) {
            throw new InvalidArgumentException(
                "Cannot block task in {$this->status->value} status"
            );
        }

        $clone = clone $this;
        $clone->status = TaskInstanceStatus::BLOCKED;

        if ($reason !== null) {
            $clone->notes = $reason;
        }

        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function unblock(): self
    {
        if ($this->status !== TaskInstanceStatus::BLOCKED) {
            return $this;
        }

        $clone = clone $this;
        $clone->status = TaskInstanceStatus::PENDING;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function assignTo(?int $userId): self
    {
        $clone = clone $this;
        $clone->assignedTo = $userId;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateDueDate(?DateTimeImmutable $dueAt): self
    {
        if ($this->status->isTerminal()) {
            return $this;
        }

        $clone = clone $this;
        $clone->dueAt = $dueAt;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateNotes(?string $notes): self
    {
        $clone = clone $this;
        $clone->notes = $notes;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function recordTimeSpent(int $minutes): self
    {
        if ($minutes < 0) {
            throw new InvalidArgumentException('Time spent cannot be negative');
        }

        $clone = clone $this;
        $clone->timeSpent = ($clone->timeSpent ?? 0) + $minutes;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateChecklistItem(int $index, bool $completed): self
    {
        $clone = clone $this;
        $clone->checklistStatus = $clone->checklistStatus ?? [];
        $clone->checklistStatus[$index] = $completed;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function resetChecklistItem(int $index): self
    {
        if (!isset($this->checklistStatus[$index])) {
            return $this;
        }

        $clone = clone $this;
        unset($clone->checklistStatus[$index]);
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function resetChecklist(): self
    {
        if ($this->checklistStatus === null || empty($this->checklistStatus)) {
            return $this;
        }

        $clone = clone $this;
        $clone->checklistStatus = [];
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function isOverdue(): bool
    {
        if ($this->dueAt === null || $this->status->isTerminal()) {
            return false;
        }

        return $this->dueAt < new DateTimeImmutable();
    }

    public function isPending(): bool
    {
        return $this->status === TaskInstanceStatus::PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === TaskInstanceStatus::IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === TaskInstanceStatus::COMPLETED;
    }

    public function isSkipped(): bool
    {
        return $this->status === TaskInstanceStatus::SKIPPED;
    }

    public function isBlocked(): bool
    {
        return $this->status === TaskInstanceStatus::BLOCKED;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function hasAssignee(): bool
    {
        return $this->assignedTo !== null;
    }

    public function hasDueDate(): bool
    {
        return $this->dueAt !== null;
    }

    public function hasNotes(): bool
    {
        return $this->notes !== null && $this->notes !== '';
    }

    public function hasTimeSpent(): bool
    {
        return $this->timeSpent !== null && $this->timeSpent > 0;
    }

    public function hasChecklist(): bool
    {
        return $this->checklistStatus !== null && !empty($this->checklistStatus);
    }

    public function isChecklistItemCompleted(int $index): bool
    {
        return $this->checklistStatus[$index] ?? false;
    }

    public function getChecklistCompletionCount(): int
    {
        if ($this->checklistStatus === null) {
            return 0;
        }

        return count(array_filter($this->checklistStatus));
    }

    public function getChecklistProgress(int $totalItems): array
    {
        $completed = $this->getChecklistCompletionCount();

        return [
            'total' => $totalItems,
            'completed' => $completed,
            'percent' => $totalItems > 0 ? round(($completed / $totalItems) * 100) : 0,
        ];
    }

    public function getDurationInMinutes(): ?int
    {
        if ($this->startedAt === null) {
            return null;
        }

        $endDate = $this->completedAt ?? new DateTimeImmutable();
        return (int) ($this->startedAt->getTimestamp() - $endDate->getTimestamp()) / 60;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInstanceId(): int
    {
        return $this->instanceId;
    }

    public function getTaskId(): int
    {
        return $this->taskId;
    }

    public function getStatus(): TaskInstanceStatus
    {
        return $this->status;
    }

    public function getDueAt(): ?DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getAssignedTo(): ?int
    {
        return $this->assignedTo;
    }

    public function getCompletedBy(): ?int
    {
        return $this->completedBy;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getChecklistStatus(): ?array
    {
        return $this->checklistStatus;
    }

    public function getTimeSpent(): ?int
    {
        return $this->timeSpent;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function equals(Entity $other): bool
    {
        return $other instanceof self
            && $this->id !== null
            && $this->id === $other->id;
    }
}
