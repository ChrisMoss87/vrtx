<?php

declare(strict_types=1);

namespace App\Domain\Campaign\ValueObjects;

/**
 * Value Object representing the status of a marketing campaign.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Get the display label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get the color for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'blue',
            self::Active => 'green',
            self::Paused => 'yellow',
            self::Completed => 'purple',
            self::Cancelled => 'red',
        };
    }

    /**
     * Check if campaign can be started from this status.
     */
    public function canBeStarted(): bool
    {
        return match ($this) {
            self::Draft, self::Scheduled, self::Paused => true,
            default => false,
        };
    }

    /**
     * Check if campaign can be paused from this status.
     */
    public function canBePaused(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if campaign can be cancelled from this status.
     */
    public function canBeCancelled(): bool
    {
        return match ($this) {
            self::Draft, self::Scheduled, self::Active, self::Paused => true,
            default => false,
        };
    }

    /**
     * Check if this status is editable.
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Draft, self::Scheduled => true,
            default => false,
        };
    }

    /**
     * Check if this status is terminal.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * Check if this status is active (sending).
     */
    public function isActive(): bool
    {
        return $this === self::Active;
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
