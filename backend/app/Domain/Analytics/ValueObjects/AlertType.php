<?php

declare(strict_types=1);

namespace App\Domain\Analytics\ValueObjects;

enum AlertType: string
{
    case THRESHOLD = 'threshold';
    case ANOMALY = 'anomaly';
    case TREND = 'trend';
    case COMPARISON = 'comparison';

    public function label(): string
    {
        return match ($this) {
            self::THRESHOLD => 'Threshold Alert',
            self::ANOMALY => 'Anomaly Detection',
            self::TREND => 'Trend Alert',
            self::COMPARISON => 'Period Comparison',
        };
    }
}
