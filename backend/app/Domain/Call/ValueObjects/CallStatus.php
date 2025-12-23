<?php

declare(strict_types=1);

namespace App\Domain\Call\ValueObjects;

/**
 * Value Object representing the status of a call.
 */
enum CallStatus: string
{
    case Initiated = 'initiated';
    case Ringing = 'ringing';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case NoAnswer = 'no_answer';
    case Busy = 'busy';
    case Canceled = 'canceled';
    case Failed = 'failed';

    /**
     * Get the display label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Initiated',
            self::Ringing => 'Ringing',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::NoAnswer => 'No Answer',
            self::Busy => 'Busy',
            self::Canceled => 'Canceled',
            self::Failed => 'Failed',
        };
    }

    /**
     * Get the color for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Initiated => 'gray',
            self::Ringing => 'yellow',
            self::InProgress => 'blue',
            self::Completed => 'green',
            self::NoAnswer => 'orange',
            self::Busy => 'orange',
            self::Canceled => 'gray',
            self::Failed => 'red',
        };
    }

    /**
     * Check if this status represents an active call.
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::Initiated, self::Ringing, self::InProgress => true,
            default => false,
        };
    }

    /**
     * Check if this status represents a completed call.
     */
    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    /**
     * Check if this status represents a missed call.
     */
    public function isMissed(): bool
    {
        return match ($this) {
            self::NoAnswer, self::Busy, self::Canceled => true,
            default => false,
        };
    }

    /**
     * Check if this status represents a terminal state.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::NoAnswer, self::Busy, self::Canceled, self::Failed => true,
            default => false,
        };
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
