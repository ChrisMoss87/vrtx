<?php

declare(strict_types=1);

namespace App\Domain\CMS\Entities;

use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class CmsMediaFolder implements Entity
{
    private function __construct(
        private ?int $id,
        private string $name,
        private string $slug,
        private ?int $parentId,
        private int $sortOrder,
        private ?int $createdBy,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        string $name,
        string $slug,
        ?int $parentId = null,
        ?int $createdBy = null,
    ): self {
        return new self(
            id: null,
            name: $name,
            slug: $slug,
            parentId: $parentId,
            sortOrder: 0,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        string $slug,
        ?int $parentId,
        int $sortOrder,
        ?int $createdBy,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            slug: $slug,
            parentId: $parentId,
            sortOrder: $sortOrder,
            createdBy: $createdBy,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSlug(): string { return $this->slug; }
    public function getParentId(): ?int { return $this->parentId; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function getCreatedBy(): ?int { return $this->createdBy; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }

    public function rename(string $name, string $slug): void
    {
        $this->name = $name;
        $this->slug = $slug;
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
