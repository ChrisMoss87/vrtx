<?php

declare(strict_types=1);

namespace App\Domain\Plugin\ValueObjects;

use InvalidArgumentException;

final readonly class PluginCategory
{
    public const SALES = 'sales';
    public const MARKETING = 'marketing';
    public const COMMUNICATION = 'communication';
    public const ANALYTICS = 'analytics';
    public const AI = 'ai';
    public const DOCUMENTS = 'documents';
    public const SERVICE = 'service';
    public const CORE = 'core';
    public const INTEGRATIONS = 'integrations';

    private const VALID_CATEGORIES = [
        self::SALES,
        self::MARKETING,
        self::COMMUNICATION,
        self::ANALYTICS,
        self::AI,
        self::DOCUMENTS,
        self::SERVICE,
        self::CORE,
        self::INTEGRATIONS,
    ];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_CATEGORIES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid plugin category "%s". Valid categories: %s',
                    $value,
                    implode(', ', self::VALID_CATEGORIES)
                )
            );
        }
    }

    public static function fromString(string $category): self
    {
        return new self($category);
    }

    public static function sales(): self
    {
        return new self(self::SALES);
    }

    public static function marketing(): self
    {
        return new self(self::MARKETING);
    }

    public static function communication(): self
    {
        return new self(self::COMMUNICATION);
    }

    public static function analytics(): self
    {
        return new self(self::ANALYTICS);
    }

    public static function ai(): self
    {
        return new self(self::AI);
    }

    public static function documents(): self
    {
        return new self(self::DOCUMENTS);
    }

    public static function service(): self
    {
        return new self(self::SERVICE);
    }

    public static function core(): self
    {
        return new self(self::CORE);
    }

    public static function integrations(): self
    {
        return new self(self::INTEGRATIONS);
    }

    public static function validCategories(): array
    {
        return self::VALID_CATEGORIES;
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
