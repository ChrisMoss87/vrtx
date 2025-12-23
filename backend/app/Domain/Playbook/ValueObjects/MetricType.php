<?php

declare(strict_types=1);

namespace App\Domain\Playbook\ValueObjects;

enum MetricType: string
{
    case COUNT = 'count';
    case SUM = 'sum';
    case AVERAGE = 'average';
    case PERCENTAGE = 'percentage';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::COUNT => 'Count',
            self::SUM => 'Sum',
            self::AVERAGE => 'Average',
            self::PERCENTAGE => 'Percentage',
            self::CUSTOM => 'Custom',
        };
    }
}
