<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use InvalidArgumentException;

/**
 * Relationship Type Value Object
 *
 * Represents the type of relationship between two modules.
 */
final readonly class RelationshipType
{
    private const ONE_TO_MANY = 'one_to_many';
    private const MANY_TO_MANY = 'many_to_many';

    private function __construct(
        private string $value,
    ) {
        $this->validate();
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function oneToMany(): self
    {
        return new self(self::ONE_TO_MANY);
    }

    public static function manyToMany(): self
    {
        return new self(self::MANY_TO_MANY);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function isOneToMany(): bool
    {
        return $this->value === self::ONE_TO_MANY;
    }

    public function isManyToMany(): bool
    {
        return $this->value === self::MANY_TO_MANY;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    private function validate(): void
    {
        if (! in_array($this->value, [self::ONE_TO_MANY, self::MANY_TO_MANY], true)) {
            throw new InvalidArgumentException(
                "Invalid relationship type: {$this->value}. Must be one of: one_to_many, many_to_many"
            );
        }
    }
}
