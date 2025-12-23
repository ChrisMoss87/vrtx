<?php

declare(strict_types=1);

namespace App\Domain\Goal\Entities;

use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;
use InvalidArgumentException;

final class GoalProgressLog implements Entity
{
    private function __construct(
        private ?int $id,
        private int $goalId,
        private DateTimeImmutable $logDate,
        private float $value,
        private float $changeAmount,
        private ?string $changeSource,
        private ?int $sourceRecordId,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {
        $this->validateInvariants();
    }

    public static function create(
        int $goalId,
        float $value,
        float $changeAmount,
        ?string $changeSource = null,
        ?int $sourceRecordId = null,
        ?DateTimeImmutable $logDate = null,
    ): self {
        return new self(
            id: null,
            goalId: $goalId,
            logDate: $logDate ?? new DateTimeImmutable('today'),
            value: $value,
            changeAmount: $changeAmount,
            changeSource: $changeSource,
            sourceRecordId: $sourceRecordId,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $goalId,
        DateTimeImmutable $logDate,
        float $value,
        float $changeAmount,
        ?string $changeSource,
        ?int $sourceRecordId,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            goalId: $goalId,
            logDate: $logDate,
            value: $value,
            changeAmount: $changeAmount,
            changeSource: $changeSource,
            sourceRecordId: $sourceRecordId,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // Business logic methods

    public function isIncrease(): bool
    {
        return $this->changeAmount > 0;
    }

    public function isDecrease(): bool
    {
        return $this->changeAmount < 0;
    }

    public function hasSource(): bool
    {
        return $this->changeSource !== null;
    }

    public function isFromSource(string $source): bool
    {
        return $this->changeSource === $source;
    }

    public function getAbsoluteChange(): float
    {
        return abs($this->changeAmount);
    }

    public function updateChangeSource(?string $changeSource, ?int $sourceRecordId = null): self
    {
        $clone = clone $this;
        $clone->changeSource = $changeSource;
        $clone->sourceRecordId = $sourceRecordId;
        $clone->updatedAt = new DateTimeImmutable();

        return $clone;
    }

    // Validation

    private function validateInvariants(): void
    {
        if ($this->changeAmount === 0.0) {
            throw new InvalidArgumentException('Change amount cannot be zero');
        }

        if ($this->changeSource !== null && empty(trim($this->changeSource))) {
            throw new InvalidArgumentException('Change source cannot be empty string');
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

    public function getLogDate(): DateTimeImmutable
    {
        return $this->logDate;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getChangeAmount(): float
    {
        return $this->changeAmount;
    }

    public function getChangeSource(): ?string
    {
        return $this->changeSource;
    }

    public function getSourceRecordId(): ?int
    {
        return $this->sourceRecordId;
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
