<?php

declare(strict_types=1);

namespace App\Domain\CMS\Entities;

use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class CmsTag implements Entity
{
    private function __construct(
        private ?int $id,
        private string $name,
        private string $slug,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(string $name, string $slug): self
    {
        return new self(
            id: null,
            name: $name,
            slug: $slug,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        string $slug,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            slug: $slug,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSlug(): string { return $this->slug; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }

    public function rename(string $name, string $slug): void
    {
        $this->name = $name;
        $this->slug = $slug;
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
