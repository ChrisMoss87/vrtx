<?php

declare(strict_types=1);

namespace App\Domain\Approval\ValueObjects;

enum ApprovalStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED, self::CANCELLED, self::EXPIRED]);
    }

    public function isPending(): bool
    {
        return in_array($this, [self::PENDING, self::IN_PROGRESS]);
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::IN_PROGRESS => 'blue',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
            self::CANCELLED => 'gray',
            self::EXPIRED => 'orange',
        };
    }
}
