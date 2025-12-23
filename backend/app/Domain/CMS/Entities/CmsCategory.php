<?php

declare(strict_types=1);

namespace App\Domain\CMS\Entities;

use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class CmsCategory implements Entity
{
    private function __construct(
        private ?int $id,
        private string $name,
        private string $slug,
        private ?string $description,
        private ?int $parentId,
        private ?string $image,
        private int $sortOrder,
        private bool $isActive,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        string $name,
        string $slug,
        ?int $parentId = null,
    ): self {
        return new self(
            id: null,
            name: $name,
            slug: $slug,
            description: null,
            parentId: $parentId,
            image: null,
            sortOrder: 0,
            isActive: true,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        string $slug,
        ?string $description,
        ?int $parentId,
        ?string $image,
        int $sortOrder,
        bool $isActive,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            slug: $slug,
            description: $description,
            parentId: $parentId,
            image: $image,
            sortOrder: $sortOrder,
            isActive: $isActive,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSlug(): string { return $this->slug; }
    public function getDescription(): ?string { return $this->description; }
    public function getParentId(): ?int { return $this->parentId; }
    public function getImage(): ?string { return $this->image; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }

    public function update(
        string $name,
        string $slug,
        ?string $description,
    ): void {
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function moveTo(?int $parentId): void
    {
        $this->parentId = $parentId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function reorder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->id !== null
            && $other->id !== null
            && $this->id === $other->id;
    }
}
