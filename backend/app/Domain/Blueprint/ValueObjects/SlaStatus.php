<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\ValueObjects;

/**
 * Represents the status of an SLA instance.
 */
enum SlaStatus: string
{
    case ACTIVE = 'active';
    case WARNING = 'warning';
    case BREACHED = 'breached';
    case COMPLETED = 'completed';
    case PAUSED = 'paused';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isBreached(): bool
    {
        return $this === self::BREACHED;
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::WARNING => 'Warning',
            self::BREACHED => 'Breached',
            self::COMPLETED => 'Completed',
            self::PAUSED => 'Paused',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::WARNING => 'yellow',
            self::BREACHED => 'red',
            self::COMPLETED => 'gray',
            self::PAUSED => 'blue',
        };
    }
}
