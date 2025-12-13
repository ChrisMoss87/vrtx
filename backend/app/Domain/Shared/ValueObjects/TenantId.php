<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing a tenant identifier.
 *
 * This provides type safety for tenant IDs in the multi-tenant system,
 * ensuring tenant isolation is maintained throughout the domain layer.
 */
final readonly class TenantId
{
    private function __construct(
        private string $value
    ) {
        if (empty(trim($value))) {
            throw new InvalidArgumentException('Tenant ID cannot be empty');
        }

        if (!preg_match('/^[a-z0-9_-]+$/i', $value)) {
            throw new InvalidArgumentException(
                'Tenant ID must contain only alphanumeric characters, underscores, and hyphens'
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

    public function equals(TenantId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
