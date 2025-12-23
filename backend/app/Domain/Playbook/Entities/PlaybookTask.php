<?php

declare(strict_types=1);

namespace App\Domain\Playbook\Entities;

use App\Domain\Playbook\ValueObjects\AssigneeType;
use App\Domain\Playbook\ValueObjects\TaskType;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class PlaybookTask implements Entity
{
    private function __construct(
        private ?int $id,
        private int $playbookId,
        private ?int $phaseId,
        private string $title,
        private ?string $description,
        private TaskType $taskType,
        private ?array $taskConfig,
        private ?int $dueDays,
        private ?int $durationEstimate,
        private bool $isRequired,
        private bool $isMilestone,
        private ?AssigneeType $assigneeType,
        private ?int $assigneeId,
        private ?string $assigneeRole,
        private array $dependencies,
        private array $checklist,
        private array $resources,
        private int $displayOrder,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $playbookId,
        string $title,
        TaskType $taskType,
        ?int $phaseId = null,
        int $displayOrder = 0,
    ): self {
        return new self(
            id: null,
            playbookId: $playbookId,
            phaseId: $phaseId,
            title: $title,
            description: null,
            taskType: $taskType,
            taskConfig: null,
            dueDays: null,
            durationEstimate: null,
            isRequired: true,
            isMilestone: false,
            assigneeType: null,
            assigneeId: null,
            assigneeRole: null,
            dependencies: [],
            checklist: [],
            resources: [],
            displayOrder: $displayOrder,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $playbookId,
        ?int $phaseId,
        string $title,
        ?string $description,
        TaskType $taskType,
        ?array $taskConfig,
        ?int $dueDays,
        ?int $durationEstimate,
        bool $isRequired,
        bool $isMilestone,
        ?AssigneeType $assigneeType,
        ?int $assigneeId,
        ?string $assigneeRole,
        array $dependencies,
        array $checklist,
        array $resources,
        int $displayOrder,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            playbookId: $playbookId,
            phaseId: $phaseId,
            title: $title,
            description: $description,
            taskType: $taskType,
            taskConfig: $taskConfig,
            dueDays: $dueDays,
            durationEstimate: $durationEstimate,
            isRequired: $isRequired,
            isMilestone: $isMilestone,
            assigneeType: $assigneeType,
            assigneeId: $assigneeId,
            assigneeRole: $assigneeRole,
            dependencies: $dependencies,
            checklist: $checklist,
            resources: $resources,
            displayOrder: $displayOrder,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function update(
        string $title,
        ?string $description = null,
        ?array $taskConfig = null,
        ?int $dueDays = null,
        ?int $durationEstimate = null,
    ): self {
        $clone = clone $this;
        $clone->title = $title;
        $clone->description = $description;
        $clone->taskConfig = $taskConfig;
        $clone->dueDays = $dueDays;
        $clone->durationEstimate = $durationEstimate;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateTaskType(TaskType $taskType): self
    {
        $clone = clone $this;
        $clone->taskType = $taskType;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function assignToPhase(?int $phaseId): self
    {
        $clone = clone $this;
        $clone->phaseId = $phaseId;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function markAsRequired(): self
    {
        if ($this->isRequired) {
            return $this;
        }

        $clone = clone $this;
        $clone->isRequired = true;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function markAsOptional(): self
    {
        if (!$this->isRequired) {
            return $this;
        }

        $clone = clone $this;
        $clone->isRequired = false;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function markAsMilestone(): self
    {
        if ($this->isMilestone) {
            return $this;
        }

        $clone = clone $this;
        $clone->isMilestone = true;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function unmarkAsMilestone(): self
    {
        if (!$this->isMilestone) {
            return $this;
        }

        $clone = clone $this;
        $clone->isMilestone = false;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function assignTo(
        AssigneeType $assigneeType,
        ?int $assigneeId = null,
        ?string $assigneeRole = null,
    ): self {
        $clone = clone $this;
        $clone->assigneeType = $assigneeType;
        $clone->assigneeId = $assigneeId;
        $clone->assigneeRole = $assigneeRole;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function clearAssignment(): self
    {
        $clone = clone $this;
        $clone->assigneeType = null;
        $clone->assigneeId = null;
        $clone->assigneeRole = null;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateDependencies(array $taskIds): self
    {
        $clone = clone $this;
        $clone->dependencies = array_values(array_unique($taskIds));
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function addDependency(int $taskId): self
    {
        if (in_array($taskId, $this->dependencies, true)) {
            return $this;
        }

        $clone = clone $this;
        $clone->dependencies[] = $taskId;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function removeDependency(int $taskId): self
    {
        $index = array_search($taskId, $this->dependencies, true);
        if ($index === false) {
            return $this;
        }

        $clone = clone $this;
        array_splice($clone->dependencies, $index, 1);
        $clone->dependencies = array_values($clone->dependencies);
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateChecklist(array $items): self
    {
        $clone = clone $this;
        $clone->checklist = array_values($items);
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function addChecklistItem(string $item): self
    {
        $clone = clone $this;
        $clone->checklist[] = $item;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function removeChecklistItem(int $index): self
    {
        if (!isset($this->checklist[$index])) {
            return $this;
        }

        $clone = clone $this;
        array_splice($clone->checklist, $index, 1);
        $clone->checklist = array_values($clone->checklist);
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateResources(array $resources): self
    {
        $clone = clone $this;
        $clone->resources = array_values($resources);
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function addResource(array $resource): self
    {
        $clone = clone $this;
        $clone->resources[] = $resource;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function removeResource(int $index): self
    {
        if (!isset($this->resources[$index])) {
            return $this;
        }

        $clone = clone $this;
        array_splice($clone->resources, $index, 1);
        $clone->resources = array_values($clone->resources);
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateDisplayOrder(int $order): self
    {
        $clone = clone $this;
        $clone->displayOrder = $order;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function hasDependencies(): bool
    {
        return !empty($this->dependencies);
    }

    public function hasChecklist(): bool
    {
        return !empty($this->checklist);
    }

    public function hasResources(): bool
    {
        return !empty($this->resources);
    }

    public function isAssigned(): bool
    {
        return $this->assigneeType !== null;
    }

    public function hasDueDays(): bool
    {
        return $this->dueDays !== null && $this->dueDays > 0;
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

    public function getPhaseId(): ?int
    {
        return $this->phaseId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getTaskType(): TaskType
    {
        return $this->taskType;
    }

    public function getTaskConfig(): ?array
    {
        return $this->taskConfig;
    }

    public function getDueDays(): ?int
    {
        return $this->dueDays;
    }

    public function getDurationEstimate(): ?int
    {
        return $this->durationEstimate;
    }

    public function getIsRequired(): bool
    {
        return $this->isRequired;
    }

    public function getIsMilestone(): bool
    {
        return $this->isMilestone;
    }

    public function getAssigneeType(): ?AssigneeType
    {
        return $this->assigneeType;
    }

    public function getAssigneeId(): ?int
    {
        return $this->assigneeId;
    }

    public function getAssigneeRole(): ?string
    {
        return $this->assigneeRole;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function getChecklist(): array
    {
        return $this->checklist;
    }

    public function getResources(): array
    {
        return $this->resources;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
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
