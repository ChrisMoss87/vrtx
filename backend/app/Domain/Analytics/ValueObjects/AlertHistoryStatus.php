<?php

declare(strict_types=1);

namespace App\Domain\Analytics\ValueObjects;

enum AlertHistoryStatus: string
{
    case TRIGGERED = 'triggered';
    case RESOLVED = 'resolved';
    case ACKNOWLEDGED = 'acknowledged';
    case MUTED = 'muted';

    public function label(): string
    {
        return match ($this) {
            self::TRIGGERED => 'Triggered',
            self::RESOLVED => 'Resolved',
            self::ACKNOWLEDGED => 'Acknowledged',
            self::MUTED => 'Muted',
        };
    }
}
