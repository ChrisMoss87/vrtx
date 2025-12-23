<?php

declare(strict_types=1);

namespace App\Domain\CMS\Entities;

use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class CmsMenu implements Entity
{
    private function __construct(
        private ?int $id,
        private string $name,
        private string $slug,
        private ?string $location,
        private array $items,
        private bool $isActive,
        private ?int $createdBy,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        string $name,
        string $slug,
        ?string $location = null,
        ?int $createdBy = null,
    ): self {
        return new self(
            id: null,
            name: $name,
            slug: $slug,
            location: $location,
            items: [],
            isActive: true,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        string $slug,
        ?string $location,
        array $items,
        bool $isActive,
        ?int $createdBy,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            slug: $slug,
            location: $location,
            items: $items,
            isActive: $isActive,
            createdBy: $createdBy,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSlug(): string { return $this->slug; }
    public function getLocation(): ?string { return $this->location; }
    public function getItems(): array { return $this->items; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedBy(): ?int { return $this->createdBy; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }

    public function update(string $name, string $slug, ?string $location): void
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->location = $location;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setItems(array $items): void
    {
        $this->items = $items;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function addItem(array $item): void
    {
        $this->items[] = $item;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function removeItem(int $index): void
    {
        if (isset($this->items[$index])) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
            $this->updatedAt = new DateTimeImmutable();
        }
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
