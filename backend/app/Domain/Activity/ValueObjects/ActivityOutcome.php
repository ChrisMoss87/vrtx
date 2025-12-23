<?php

declare(strict_types=1);

namespace App\Domain\Activity\ValueObjects;

/**
 * Value Object representing the outcome of a call or meeting activity.
 */
enum ActivityOutcome: string
{
    case Completed = 'completed';
    case NoAnswer = 'no_answer';
    case LeftVoicemail = 'left_voicemail';
    case Busy = 'busy';
    case WrongNumber = 'wrong_number';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';

    /**
     * Get the display label for this outcome.
     */
    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completed',
            self::NoAnswer => 'No Answer',
            self::LeftVoicemail => 'Left Voicemail',
            self::Busy => 'Busy',
            self::WrongNumber => 'Wrong Number',
            self::Rescheduled => 'Rescheduled',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Check if this outcome represents a successful contact.
     */
    public function isSuccessful(): bool
    {
        return match ($this) {
            self::Completed, self::LeftVoicemail => true,
            default => false,
        };
    }

    /**
     * Check if this outcome requires follow-up.
     */
    public function requiresFollowUp(): bool
    {
        return match ($this) {
            self::NoAnswer, self::Busy, self::Rescheduled => true,
            default => false,
        };
    }

    /**
     * Check if this outcome represents a negative result.
     */
    public function isNegative(): bool
    {
        return match ($this) {
            self::WrongNumber, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * Get all outcomes as an associative array.
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
