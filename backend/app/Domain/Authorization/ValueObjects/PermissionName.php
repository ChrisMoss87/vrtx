<?php

declare(strict_types=1);

namespace App\Domain\Authorization\ValueObjects;

use InvalidArgumentException;

final readonly class PermissionName
{
    public function __construct(
        private string $value,
    ) {
        if (empty(trim($value))) {
            throw new InvalidArgumentException('Permission name cannot be empty');
        }

        if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/', $value)) {
            throw new InvalidArgumentException(
                "Invalid permission name format: {$value}. Expected format: 'category.action' (e.g., 'modules.view')"
            );
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Get the category part of the permission (e.g., 'modules' from 'modules.view')
     */
    public function category(): string
    {
        $parts = explode('.', $this->value);

        return $parts[0];
    }

    /**
     * Get the action part of the permission (e.g., 'view' from 'modules.view')
     */
    public function action(): ?string
    {
        $parts = explode('.', $this->value);

        return $parts[1] ?? null;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
