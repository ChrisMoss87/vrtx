<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

use InvalidArgumentException;

final readonly class Rating
{
    private function __construct(
        private float $value,
        private ?string $comment
    ) {
        if ($value < 1.0 || $value > 5.0) {
            throw new InvalidArgumentException('Rating must be between 1.0 and 5.0');
        }
    }

    public static function fromValue(float $value, ?string $comment = null): self
    {
        return new self($value, $comment);
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function isPositive(): bool
    {
        return $this->value >= 4.0;
    }

    public function isNegative(): bool
    {
        return $this->value < 3.0;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
