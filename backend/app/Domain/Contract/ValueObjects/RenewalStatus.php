<?php

declare(strict_types=1);

namespace App\Domain\Contract\ValueObjects;

/**
 * Value Object representing the renewal status of a contract.
 */
enum RenewalStatus: string
{
    case NotApplicable = 'not_applicable';
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Approved = 'approved';
    case Declined = 'declined';
    case Completed = 'completed';

    /**
     * Get the display label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotApplicable => 'Not Applicable',
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
            self::Completed => 'Completed',
        };
    }

    /**
     * Get the color for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::NotApplicable => 'gray',
            self::Pending => 'yellow',
            self::InProgress => 'blue',
            self::Approved => 'green',
            self::Declined => 'red',
            self::Completed => 'purple',
        };
    }

    /**
     * Check if renewal is in progress.
     */
    public function isInProgress(): bool
    {
        return match ($this) {
            self::Pending, self::InProgress, self::Approved => true,
            default => false,
        };
    }

    /**
     * Check if renewal is complete.
     */
    public function isComplete(): bool
    {
        return match ($this) {
            self::Completed, self::Declined => true,
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
