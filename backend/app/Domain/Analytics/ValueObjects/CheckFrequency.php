<?php

declare(strict_types=1);

namespace App\Domain\Analytics\ValueObjects;

enum CheckFrequency: string
{
    case REALTIME = 'realtime';
    case HOURLY = 'hourly';
    case DAILY = 'daily';
    case WEEKLY = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::REALTIME => 'Real-time',
            self::HOURLY => 'Every Hour',
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
        };
    }
}
