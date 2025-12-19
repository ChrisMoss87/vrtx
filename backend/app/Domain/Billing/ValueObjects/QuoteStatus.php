<?php

declare(strict_types=1);

namespace App\Domain\Billing\ValueObjects;

/**
 * Enum representing quote statuses.
 */
enum QuoteStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case VIEWED = 'viewed';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    /**
     * Get human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent',
            self::VIEWED => 'Viewed',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Rejected',
            self::EXPIRED => 'Expired',
        };
    }

    /**
     * Check if the quote can be edited.
     */
    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if the quote can be sent.
     */
    public function canBeSent(): bool
    {
        return in_array($this, [self::DRAFT, self::SENT, self::VIEWED]);
    }

    /**
     * Check if the quote can be accepted or rejected.
     */
    public function canBeAccepted(): bool
    {
        return !in_array($this, [self::ACCEPTED, self::REJECTED, self::EXPIRED]);
    }

    /**
     * Check if this is a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::ACCEPTED, self::REJECTED, self::EXPIRED]);
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SENT => 'blue',
            self::VIEWED => 'purple',
            self::ACCEPTED => 'green',
            self::REJECTED => 'red',
            self::EXPIRED => 'orange',
        };
    }
}
