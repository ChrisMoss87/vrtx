<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing a user identifier.
 *
 * This provides type safety for user IDs throughout the domain layer,
 * preventing accidental mixing of different ID types.
 */
final readonly class UserId
{
    private function __construct(
        private int $value
    ) {
        if ($value < 1) {
            throw new InvalidArgumentException('User ID must be a positive integer');
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public static function fromString(string $value): self
    {
        if (!is_numeric($value) || (int) $value < 1) {
            throw new InvalidArgumentException('User ID must be a positive integer string');
        }

        return new self((int) $value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(UserId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
