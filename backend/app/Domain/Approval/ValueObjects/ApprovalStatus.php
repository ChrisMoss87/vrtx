<?php

declare(strict_types=1);

namespace App\Domain\Approval\ValueObjects;

/**
 * Value Object representing the status of an approval request.
 */
enum ApprovalStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * Get the display label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    /**
     * Get the color for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::InProgress => 'blue',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Cancelled => 'gray',
            self::Expired => 'orange',
        };
    }

    /**
     * Check if approval is still pending (awaiting action).
     */
    public function isPending(): bool
    {
        return match ($this) {
            self::Pending, self::InProgress => true,
            default => false,
        };
    }

    /**
     * Check if this is a terminal (final) status.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Approved, self::Rejected, self::Cancelled, self::Expired => true,
            default => false,
        };
    }

    /**
     * Check if approval was successful.
     */
    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Check if approval was rejected.
     */
    public function isRejected(): bool
    {
        return $this === self::Rejected;
    }

    /**
     * Check if approval can be actioned (approved/rejected).
     */
    public function canBeActioned(): bool
    {
        return $this->isPending();
    }

    /**
     * Check if approval can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->isPending();
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
