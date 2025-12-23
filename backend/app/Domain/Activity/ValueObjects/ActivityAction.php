<?php

declare(strict_types=1);

namespace App\Domain\Activity\ValueObjects;

/**
 * Value Object representing the action performed in an activity.
 */
enum ActivityAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Completed = 'completed';
    case Sent = 'sent';
    case Received = 'received';
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';

    /**
     * Get the display label for this action.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
            self::Completed => 'Completed',
            self::Sent => 'Sent',
            self::Received => 'Received',
            self::Scheduled => 'Scheduled',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get the past tense verb for this action.
     */
    public function pastTense(): string
    {
        return match ($this) {
            self::Created => 'created',
            self::Updated => 'updated',
            self::Deleted => 'deleted',
            self::Completed => 'completed',
            self::Sent => 'sent',
            self::Received => 'received',
            self::Scheduled => 'scheduled',
            self::Cancelled => 'cancelled',
        };
    }

    /**
     * Check if this action represents a terminal state.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Deleted, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * Get all actions as an associative array.
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
