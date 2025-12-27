<?php

declare(strict_types=1);

namespace App\Domain\Authorization\ValueObjects;

use InvalidArgumentException;

final readonly class RoleId
{
    public function __construct(
        private int $value,
    ) {
        if ($value <= 0) {
            throw new InvalidArgumentException('Role ID must be a positive integer');
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
