<?php

declare(strict_types=1);

namespace App\Domain\Cadence\ValueObjects;

enum DelayType: string
{
    case IMMEDIATE = 'immediate';
    case HOURS = 'hours';
    case DAYS = 'days';
    case BUSINESS_DAYS = 'business_days';

    public function label(): string
    {
        return match ($this) {
            self::IMMEDIATE => 'Immediate',
            self::HOURS => 'Hours',
            self::DAYS => 'Days',
            self::BUSINESS_DAYS => 'Business Days',
        };
    }

    public function calculateSeconds(int $value): int
    {
        return match ($this) {
            self::IMMEDIATE => 0,
            self::HOURS => $value * 3600,
            self::DAYS => $value * 86400,
            self::BUSINESS_DAYS => (int) ($value * 1.4 * 86400), // Rough approximation
        };
    }
}
