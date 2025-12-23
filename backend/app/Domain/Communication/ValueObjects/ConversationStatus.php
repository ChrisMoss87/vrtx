<?php

declare(strict_types=1);

namespace App\Domain\Communication\ValueObjects;

enum ConversationStatus: string
{
    case OPEN = 'open';
    case PENDING = 'pending';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::PENDING => 'Pending',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'blue',
            self::PENDING => 'yellow',
            self::RESOLVED => 'green',
            self::CLOSED => 'gray',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::OPEN, self::PENDING]);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::RESOLVED, self::CLOSED]);
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::OPEN => true, // Can go anywhere
            self::PENDING => in_array($newStatus, [self::OPEN, self::RESOLVED, self::CLOSED]),
            self::RESOLVED => in_array($newStatus, [self::OPEN, self::CLOSED]),
            self::CLOSED => $newStatus === self::OPEN, // Can only reopen
        };
    }
}
