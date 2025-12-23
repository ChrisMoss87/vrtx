<?php

declare(strict_types=1);

namespace App\Domain\Goal\Entities;

use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;
use InvalidArgumentException;

final class GoalMilestone implements Entity
{
    private function __construct(
        private ?int $id,
        private int $goalId,
        private string $name,
        private float $targetValue,
        private ?DateTimeImmutable $targetDate,
        private bool $isAchieved,
        private ?DateTimeImmutable $achievedAt,
        private int $displayOrder,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {
        $this->validateInvariants();
    }

    public static function create(
        int $goalId,
        string $name,
        float $targetValue,
        int $displayOrder,
        ?DateTimeImmutable $targetDate = null,
    ): self {
        return new self(
            id: null,
            goalId: $goalId,
            name: $name,
            targetValue: $targetValue,
            targetDate: $targetDate,
            isAchieved: false,
            achievedAt: null,
            displayOrder: $displayOrder,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $goalId,
        string $name,
        float $targetValue,
        ?DateTimeImmutable $targetDate,
        bool $isAchieved,
        ?DateTimeImmutable $achievedAt,
        int $displayOrder,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            goalId: $goalId,
            name: $name,
            targetValue: $targetValue,
            targetDate: $targetDate,
            isAchieved: $isAchieved,
            achievedAt: $achievedAt,
            displayOrder: $displayOrder,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // Business logic methods

    public function markAchieved(): self
    {
        if ($this->isAchieved) {
            return $this;
        }

        $clone = clone $this;
        $clone->isAchieved = true;
        $clone->achievedAt = new DateTimeImmutable();
        $clone->updatedAt = new DateTimeImmutable();

        return $clone;
    }

    public function updateName(string $name): self
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Milestone name cannot be empty');
        }

        $clone = clone $this;
        $clone->name = $name;
        $clone->updatedAt = new DateTimeImmutable();

        return $clone;
    }

    public function updateTargetValue(float $targetValue): self
    {
        if ($targetValue <= 0) {
            throw new InvalidArgumentException('Target value must be greater than zero');
        }

        $clone = clone $this;
        $clone->targetValue = $targetValue;
        $clone->updatedAt = new DateTimeImmutable();

        return $clone;
    }

    public function updateTargetDate(?DateTimeImmutable $targetDate): self
    {
        $clone = clone $this;
        $clone->targetDate = $targetDate;
        $clone->updatedAt = new DateTimeImmutable();

        return $clone;
    }

    public function updateDisplayOrder(int $displayOrder): self
    {
        if ($displayOrder < 0) {
            throw new InvalidArgumentException('Display order cannot be negative');
        }

        $clone = clone $this;
        $clone->displayOrder = $displayOrder;
        $clone->updatedAt = new DateTimeImmutable();

        return $clone;
    }

    public function resetAchievement(): self
    {
        if (!$this->isAchieved) {
            return $this;
        }

        $clone = clone $this;
        $clone->isAchieved = false;
        $clone->achievedAt = null;
        $clone->updatedAt = new DateTimeImmutable();

        return $clone;
    }

    // Computed properties

    public function isOverdue(): bool
    {
        if ($this->isAchieved || $this->targetDate === null) {
            return false;
        }

        $today = new DateTimeImmutable('today');
        return $today > $this->targetDate;
    }

    public function getDaysUntilTarget(): ?int
    {
        if ($this->targetDate === null) {
            return null;
        }

        if ($this->isAchieved) {
            return null;
        }

        $today = new DateTimeImmutable('today');
        if ($today > $this->targetDate) {
            return 0;
        }

        return (int) $today->diff($this->targetDate)->days;
    }

    public function getDaysToAchievement(): ?int
    {
        if (!$this->isAchieved || $this->createdAt === null || $this->achievedAt === null) {
            return null;
        }

        return (int) $this->createdAt->diff($this->achievedAt)->days;
    }

    // Validation

    private function validateInvariants(): void
    {
        if (empty(trim($this->name))) {
            throw new InvalidArgumentException('Milestone name cannot be empty');
        }

        if ($this->targetValue <= 0) {
            throw new InvalidArgumentException('Target value must be greater than zero');
        }

        if ($this->displayOrder < 0) {
            throw new InvalidArgumentException('Display order cannot be negative');
        }

        if ($this->isAchieved && $this->achievedAt === null) {
            throw new InvalidArgumentException('Achieved milestones must have an achieved date');
        }

        if (!$this->isAchieved && $this->achievedAt !== null) {
            throw new InvalidArgumentException('Non-achieved milestones cannot have an achieved date');
        }
    }

    // Getters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGoalId(): int
    {
        return $this->goalId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTargetValue(): float
    {
        return $this->targetValue;
    }

    public function getTargetDate(): ?DateTimeImmutable
    {
        return $this->targetDate;
    }

    public function isAchieved(): bool
    {
        return $this->isAchieved;
    }

    public function getAchievedAt(): ?DateTimeImmutable
    {
        return $this->achievedAt;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->id !== null && $this->id === $other->id;
    }
}
