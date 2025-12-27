<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Entities;

use App\Domain\Authorization\ValueObjects\RoleId;
use DateTimeImmutable;
use DomainException;

final class Role
{
    /**
     * @param array<string> $permissions Array of permission names
     */
    private function __construct(
        private ?RoleId $id,
        private string $name,
        private ?string $displayName,
        private ?string $description,
        private bool $isSystem,
        private array $permissions,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        string $name,
        ?string $displayName = null,
        ?string $description = null,
        bool $isSystem = false,
        array $permissions = [],
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: null,
            name: strtolower(trim($name)),
            displayName: $displayName,
            description: $description,
            isSystem: $isSystem,
            permissions: $permissions,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        ?string $displayName,
        ?string $description,
        bool $isSystem,
        array $permissions,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: RoleId::fromInt($id),
            name: $name,
            displayName: $displayName,
            description: $description,
            isSystem: $isSystem,
            permissions: $permissions,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function getId(): ?RoleId
    {
        return $this->id;
    }

    public function getIdValue(): ?int
    {
        return $this->id?->value();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    /**
     * @return array<string>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isAdmin(): bool
    {
        return $this->name === 'admin';
    }

    public function withDisplayName(string $displayName): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            displayName: $displayName,
            description: $this->description,
            isSystem: $this->isSystem,
            permissions: $this->permissions,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withDescription(string $description): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            displayName: $this->displayName,
            description: $description,
            isSystem: $this->isSystem,
            permissions: $this->permissions,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param array<string> $permissions
     */
    public function withPermissions(array $permissions): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            displayName: $this->displayName,
            description: $this->description,
            isSystem: $this->isSystem,
            permissions: array_values(array_unique($permissions)),
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function grantPermission(string $permission): self
    {
        if ($this->hasPermission($permission)) {
            return $this;
        }

        $permissions = $this->permissions;
        $permissions[] = $permission;

        return $this->withPermissions($permissions);
    }

    public function revokePermission(string $permission): self
    {
        if (!$this->hasPermission($permission)) {
            return $this;
        }

        return $this->withPermissions(
            array_filter($this->permissions, fn ($p) => $p !== $permission)
        );
    }

    public function canBeDeleted(): bool
    {
        return !$this->isSystem;
    }

    public function ensureCanBeDeleted(): void
    {
        if (!$this->canBeDeleted()) {
            throw new DomainException("System role '{$this->name}' cannot be deleted");
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id?->value(),
            'name' => $this->name,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'is_system' => $this->isSystem,
            'permissions' => $this->permissions,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
