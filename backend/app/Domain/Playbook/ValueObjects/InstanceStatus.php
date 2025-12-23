<?php

declare(strict_types=1);

namespace App\Domain\Playbook\ValueObjects;

enum InstanceStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::CANCELLED => true,
            default => false,
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array($newStatus, [self::ACTIVE, self::CANCELLED]),
            self::ACTIVE => in_array($newStatus, [self::PAUSED, self::COMPLETED, self::CANCELLED]),
            self::PAUSED => in_array($newStatus, [self::ACTIVE, self::CANCELLED]),
            self::COMPLETED, self::CANCELLED => false,
        };
    }
}
