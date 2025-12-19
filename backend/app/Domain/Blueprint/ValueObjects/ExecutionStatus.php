<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\ValueObjects;

/**
 * Represents the status of a transition execution.
 */
enum ExecutionStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case AWAITING_APPROVAL = 'awaiting_approval';
    case AWAITING_REQUIREMENTS = 'awaiting_requirements';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case ROLLED_BACK = 'rolled_back';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isInProgress(): bool
    {
        return in_array($this, [self::IN_PROGRESS, self::AWAITING_APPROVAL, self::AWAITING_REQUIREMENTS]);
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isFailed(): bool
    {
        return in_array($this, [self::FAILED, self::CANCELLED, self::ROLLED_BACK]);
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::AWAITING_APPROVAL => 'Awaiting Approval',
            self::AWAITING_REQUIREMENTS => 'Awaiting Requirements',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::ROLLED_BACK => 'Rolled Back',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::IN_PROGRESS, self::AWAITING_APPROVAL, self::AWAITING_REQUIREMENTS => 'blue',
            self::COMPLETED => 'green',
            self::FAILED, self::CANCELLED, self::ROLLED_BACK => 'red',
        };
    }
}
