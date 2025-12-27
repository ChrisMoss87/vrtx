<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Entities;

use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;
use InvalidArgumentException;

final class DocumentFolder implements Entity
{
    private function __construct(
        private ?int $id,
        private string $name,
        private ?int $parentId,
        private int $ownerId,
        private ?string $color,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        string $name,
        int $ownerId,
        ?int $parentId = null,
        ?string $color = null,
    ): self {
        $name = trim($name);
        if (empty($name)) {
            throw new InvalidArgumentException('Folder name cannot be empty');
        }

        if (mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Folder name cannot exceed 255 characters');
        }

        if ($ownerId <= 0) {
            throw new InvalidArgumentException('Owner ID must be positive');
        }

        if ($color !== null && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new InvalidArgumentException('Color must be a valid hex color code');
        }

        return new self(
            id: null,
            name: $name,
            parentId: $parentId,
            ownerId: $ownerId,
            color: $color,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        ?int $parentId,
        int $ownerId,
        ?string $color,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            parentId: $parentId,
            ownerId: $ownerId,
            color: $color,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function rename(string $name): self
    {
        $name = trim($name);
        if (empty($name)) {
            throw new InvalidArgumentException('Folder name cannot be empty');
        }

        if (mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Folder name cannot exceed 255 characters');
        }

        if ($this->name === $name) {
            return $this;
        }

        $new = clone $this;
        $new->name = $name;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function move(?int $parentId): self
    {
        if ($this->parentId === $parentId) {
            return $this;
        }

        if ($parentId !== null && $parentId === $this->id) {
            throw new InvalidArgumentException('Cannot move folder into itself');
        }

        $new = clone $this;
        $new->parentId = $parentId;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function changeColor(?string $color): self
    {
        if ($color !== null && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new InvalidArgumentException('Color must be a valid hex color code');
        }

        if ($this->color === $color) {
            return $this;
        }

        $new = clone $this;
        $new->color = $color;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function isRoot(): bool
    {
        return $this->parentId === null;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    public function getColor(): ?string
    {
        return $this->color;
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

        if ($this->id === null || $other->id === null) {
            return false;
        }

        return $this->id === $other->id;
    }
}
