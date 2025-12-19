<?php

declare(strict_types=1);

namespace App\Domain\Billing\ValueObjects;

/**
 * Enum representing invoice statuses.
 */
enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case VIEWED = 'viewed';
    case PAID = 'paid';
    case PARTIAL = 'partial';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';

    /**
     * Get human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent',
            self::VIEWED => 'Viewed',
            self::PAID => 'Paid',
            self::PARTIAL => 'Partially Paid',
            self::OVERDUE => 'Overdue',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Check if the invoice can be edited.
     */
    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if the invoice can be sent.
     */
    public function canBeSent(): bool
    {
        return in_array($this, [self::DRAFT, self::SENT, self::VIEWED, self::PARTIAL]);
    }

    /**
     * Check if payments can be recorded.
     */
    public function canRecordPayment(): bool
    {
        return !in_array($this, [self::PAID, self::CANCELLED]);
    }

    /**
     * Check if this is a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::PAID, self::CANCELLED]);
    }

    /**
     * Check if this status indicates an unpaid invoice.
     */
    public function isUnpaid(): bool
    {
        return !in_array($this, [self::PAID, self::CANCELLED]);
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
            self::PAID => 'green',
            self::PARTIAL => 'yellow',
            self::OVERDUE => 'red',
            self::CANCELLED => 'gray',
        };
    }
}
