<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

enum ConversationStatus: string
{
    case OPEN = 'open';
    case PENDING = 'pending';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::PENDING => 'Pending',
            self::CLOSED => 'Closed',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::OPEN;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isClosed(): bool
    {
        return $this === self::CLOSED;
    }

    public function canBeClosed(): bool
    {
        return $this !== self::CLOSED;
    }

    public function canBeReopened(): bool
    {
        return $this === self::CLOSED;
    }
}
