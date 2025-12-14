<?php

declare(strict_types=1);

namespace App\Domain\Approval\ValueObjects;

enum StepStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SKIPPED = 'skipped';
    case DELEGATED = 'delegated';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::SKIPPED => 'Skipped',
            self::DELEGATED => 'Delegated',
        };
    }

    public function isDecided(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED, self::SKIPPED]);
    }

    public function isPositive(): bool
    {
        return in_array($this, [self::APPROVED, self::SKIPPED]);
    }
}
