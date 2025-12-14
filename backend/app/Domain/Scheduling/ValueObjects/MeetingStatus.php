<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\ValueObjects;

/**
 * Enum representing meeting statuses.
 */
enum MeetingStatus: string
{
    case SCHEDULED = 'scheduled';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case RESCHEDULED = 'rescheduled';
    case NO_SHOW = 'no_show';

    /**
     * Get human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Scheduled',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::RESCHEDULED => 'Rescheduled',
            self::NO_SHOW => 'No Show',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::SCHEDULED => 'blue',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
            self::RESCHEDULED => 'yellow',
            self::NO_SHOW => 'orange',
        };
    }

    /**
     * Check if this status indicates the meeting is active.
     */
    public function isActive(): bool
    {
        return $this === self::SCHEDULED;
    }

    /**
     * Check if this status indicates the meeting is finished.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::NO_SHOW]);
    }

    /**
     * Check if this status allows cancellation.
     */
    public function canBeCancelled(): bool
    {
        return $this === self::SCHEDULED;
    }

    /**
     * Check if this status allows rescheduling.
     */
    public function canBeRescheduled(): bool
    {
        return $this === self::SCHEDULED;
    }

    /**
     * Check if the status can transition to the given status.
     */
    public function canTransitionTo(MeetingStatus $target): bool
    {
        return match ($this) {
            self::SCHEDULED => in_array($target, [self::COMPLETED, self::CANCELLED, self::RESCHEDULED, self::NO_SHOW]),
            self::RESCHEDULED => in_array($target, [self::SCHEDULED, self::CANCELLED]),
            self::COMPLETED, self::CANCELLED, self::NO_SHOW => false, // Terminal states
        };
    }
}
