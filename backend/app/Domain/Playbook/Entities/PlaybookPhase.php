<?php

declare(strict_types=1);

namespace App\Domain\Playbook\Entities;

use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class PlaybookPhase implements Entity
{
    private function __construct(
        private ?int $id,
        private int $playbookId,
        private string $name,
        private ?string $description,
        private ?int $targetDays,
        private int $displayOrder,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $playbookId,
        string $name,
        int $displayOrder = 0,
    ): self {
        return new self(
            id: null,
            playbookId: $playbookId,
            name: $name,
            description: null,
            targetDays: null,
            displayOrder: $displayOrder,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $playbookId,
        string $name,
        ?string $description,
        ?int $targetDays,
        int $displayOrder,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            playbookId: $playbookId,
            name: $name,
            description: $description,
            targetDays: $targetDays,
            displayOrder: $displayOrder,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function update(
        string $name,
        ?string $description = null,
        ?int $targetDays = null,
    ): self {
        $clone = clone $this;
        $clone->name = $name;
        $clone->description = $description;
        $clone->targetDays = $targetDays;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateDisplayOrder(int $order): self
    {
        if ($this->displayOrder === $order) {
            return $this;
        }

        $clone = clone $this;
        $clone->displayOrder = $order;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function hasTargetDuration(): bool
    {
        return $this->targetDays !== null && $this->targetDays > 0;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getTargetDays(): ?int
    {
        return $this->targetDays;
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
