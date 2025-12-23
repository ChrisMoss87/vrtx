<?php

declare(strict_types=1);

namespace App\Domain\Cadence\ValueObjects;

enum ExecutionStatus: string
{
    case SCHEDULED = 'scheduled';
    case EXECUTING = 'executing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Scheduled',
            self::EXECUTING => 'Executing',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::SKIPPED => 'Skipped',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function isDue(): bool
    {
        return $this === self::SCHEDULED;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::FAILED,
            self::SKIPPED,
            self::CANCELLED,
        ], true);
    }

    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }
}
