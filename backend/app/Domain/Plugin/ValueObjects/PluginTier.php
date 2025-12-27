<?php

declare(strict_types=1);

namespace App\Domain\Plugin\ValueObjects;

use InvalidArgumentException;

final readonly class PluginTier
{
    public const CORE = 'core';
    public const PROFESSIONAL = 'professional';
    public const ADVANCED = 'advanced';
    public const ENTERPRISE = 'enterprise';

    private const VALID_TIERS = [
        self::CORE,
        self::PROFESSIONAL,
        self::ADVANCED,
        self::ENTERPRISE,
    ];

    private const TIER_HIERARCHY = [
        self::CORE => 0,
        self::PROFESSIONAL => 1,
        self::ADVANCED => 2,
        self::ENTERPRISE => 3,
    ];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_TIERS, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid plugin tier "%s". Valid tiers: %s',
                    $value,
                    implode(', ', self::VALID_TIERS)
                )
            );
        }
    }

    public static function fromString(string $tier): self
    {
        return new self($tier);
    }

    public static function core(): self
    {
        return new self(self::CORE);
    }

    public static function professional(): self
    {
        return new self(self::PROFESSIONAL);
    }

    public static function advanced(): self
    {
        return new self(self::ADVANCED);
    }

    public static function enterprise(): self
    {
        return new self(self::ENTERPRISE);
    }

    public static function validTiers(): array
    {
        return self::VALID_TIERS;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function level(): int
    {
        return self::TIER_HIERARCHY[$this->value];
    }

    public function isAtLeast(self $other): bool
    {
        return $this->level() >= $other->level();
    }

    public function isHigherThan(self $other): bool
    {
        return $this->level() > $other->level();
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
