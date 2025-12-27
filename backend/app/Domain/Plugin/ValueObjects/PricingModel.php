<?php

declare(strict_types=1);

namespace App\Domain\Plugin\ValueObjects;

use InvalidArgumentException;

final readonly class PricingModel
{
    public const PER_USER = 'per_user';
    public const FLAT = 'flat';
    public const USAGE = 'usage';
    public const INCLUDED = 'included';

    private const VALID_MODELS = [
        self::PER_USER,
        self::FLAT,
        self::USAGE,
        self::INCLUDED,
    ];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_MODELS, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid pricing model "%s". Valid models: %s',
                    $value,
                    implode(', ', self::VALID_MODELS)
                )
            );
        }
    }

    public static function fromString(string $model): self
    {
        return new self($model);
    }

    public static function perUser(): self
    {
        return new self(self::PER_USER);
    }

    public static function flat(): self
    {
        return new self(self::FLAT);
    }

    public static function usage(): self
    {
        return new self(self::USAGE);
    }

    public static function included(): self
    {
        return new self(self::INCLUDED);
    }

    public static function validModels(): array
    {
        return self::VALID_MODELS;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isPerUser(): bool
    {
        return $this->value === self::PER_USER;
    }

    public function isFlat(): bool
    {
        return $this->value === self::FLAT;
    }

    public function isUsageBased(): bool
    {
        return $this->value === self::USAGE;
    }

    public function isIncluded(): bool
    {
        return $this->value === self::INCLUDED;
    }

    public function requiresPayment(): bool
    {
        return $this->value !== self::INCLUDED;
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
