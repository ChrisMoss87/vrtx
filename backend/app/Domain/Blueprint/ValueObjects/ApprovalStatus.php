<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\ValueObjects;

/**
 * Represents the status of an approval request.
 */
enum ApprovalStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isResolved(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED, self::CANCELLED, self::EXPIRED]);
    }

    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
            self::CANCELLED => 'gray',
            self::EXPIRED => 'orange',
        };
    }
}
