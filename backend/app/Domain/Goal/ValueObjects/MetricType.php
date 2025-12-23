<?php

declare(strict_types=1);

namespace App\Domain\Goal\ValueObjects;

/**
 * Represents different types of metrics that can be tracked for goals.
 */
enum MetricType: string
{
    case COUNT = 'count';
    case REVENUE = 'revenue';
    case PERCENTAGE = 'percentage';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::COUNT => 'Count',
            self::REVENUE => 'Revenue',
            self::PERCENTAGE => 'Percentage',
            self::CUSTOM => 'Custom',
        };
    }

    public function isCount(): bool
    {
        return $this === self::COUNT;
    }

    public function isRevenue(): bool
    {
        return $this === self::REVENUE;
    }

    public function isPercentage(): bool
    {
        return $this === self::PERCENTAGE;
    }

    public function isCustom(): bool
    {
        return $this === self::CUSTOM;
    }

    public function requiresCurrency(): bool
    {
        return $this === self::REVENUE;
    }

    public function hasMaxValue(): bool
    {
        return $this === self::PERCENTAGE;
    }

    public function maxValue(): ?float
    {
        return $this === self::PERCENTAGE ? 100.0 : null;
    }
}
