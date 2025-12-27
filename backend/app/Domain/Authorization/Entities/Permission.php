<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Entities;

use App\Domain\Authorization\ValueObjects\PermissionName;
use DateTimeImmutable;

final class Permission
{
    private function __construct(
        private ?int $id,
        private PermissionName $name,
        private ?string $displayName,
        private ?string $category,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        PermissionName $name,
        ?string $displayName = null,
        ?string $category = null,
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: null,
            name: $name,
            displayName: $displayName,
            category: $category ?? $name->category(),
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        ?string $displayName,
        ?string $category,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: PermissionName::fromString($name),
            displayName: $displayName,
            category: $category,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): PermissionName
    {
        return $this->name;
    }

    public function getNameValue(): string
    {
        return $this->name->value();
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function withDisplayName(string $displayName): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            displayName: $displayName,
            category: $this->category,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name->value(),
            'display_name' => $this->displayName,
            'category' => $this->category,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
