<?php

declare(strict_types=1);

namespace App\Domain\Plugin\ValueObjects;

use InvalidArgumentException;

final readonly class PluginId
{
    private function __construct(
        private int $value
    ) {
        if ($value < 1) {
            throw new InvalidArgumentException('Plugin ID must be a positive integer');
        }
    }

    public static function fromInt(int $id): self
    {
        return new self($id);
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
