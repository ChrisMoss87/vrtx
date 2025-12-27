<?php

declare(strict_types=1);

namespace App\Domain\Plugin\ValueObjects;

use InvalidArgumentException;

final readonly class PluginSlug
{
    private const MAX_LENGTH = 50;
    private const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private function __construct(
        private string $value
    ) {
        if (strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Plugin slug must not exceed %d characters', self::MAX_LENGTH)
            );
        }

        if (!preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException(
                'Plugin slug must be lowercase alphanumeric with hyphens (e.g., "forecasting-pro")'
            );
        }
    }

    public static function fromString(string $slug): self
    {
        return new self($slug);
    }

    public function value(): string
    {
        return $this->value;
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
