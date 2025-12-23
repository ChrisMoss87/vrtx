<?php

declare(strict_types=1);

namespace App\Domain\Inbox\ValueObjects;

/**
 * Value Object representing the status of an inbox conversation.
 */
enum ConversationStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * Get the display label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Pending => 'Pending',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    /**
     * Get the color for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::Pending => 'yellow',
            self::Resolved => 'green',
            self::Closed => 'gray',
        };
    }

    /**
     * Get the icon for this status.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Open => 'inbox',
            self::Pending => 'clock',
            self::Resolved => 'check-circle',
            self::Closed => 'archive',
        };
    }

    /**
     * Check if conversation is active (requires attention).
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::Open, self::Pending => true,
            default => false,
        };
    }

    /**
     * Check if conversation is complete.
     */
    public function isComplete(): bool
    {
        return match ($this) {
            self::Resolved, self::Closed => true,
            default => false,
        };
    }

    /**
     * Check if conversation can be reopened.
     */
    public function canReopen(): bool
    {
        return $this->isComplete();
    }

    /**
     * Check if conversation can be resolved.
     */
    public function canResolve(): bool
    {
        return $this->isActive();
    }

    /**
     * Get all statuses as an associative array.
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }
        return $result;
    }
}
