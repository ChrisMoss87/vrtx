<?php

declare(strict_types=1);

namespace App\Domain\Cadence\ValueObjects;

enum ExecutionResult: string
{
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case OPENED = 'opened';
    case CLICKED = 'clicked';
    case REPLIED = 'replied';
    case BOUNCED = 'bounced';
    case FAILED = 'failed';
    case COMPLETED = 'completed';
    case SKIPPED = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::SENT => 'Sent',
            self::DELIVERED => 'Delivered',
            self::OPENED => 'Opened',
            self::CLICKED => 'Clicked',
            self::REPLIED => 'Replied',
            self::BOUNCED => 'Bounced',
            self::FAILED => 'Failed',
            self::COMPLETED => 'Completed',
            self::SKIPPED => 'Skipped',
        };
    }

    public function isEngagement(): bool
    {
        return in_array($this, [
            self::OPENED,
            self::CLICKED,
            self::REPLIED,
        ], true);
    }

    public function isPositive(): bool
    {
        return in_array($this, [
            self::SENT,
            self::DELIVERED,
            self::OPENED,
            self::CLICKED,
            self::REPLIED,
            self::COMPLETED,
        ], true);
    }

    public function isNegative(): bool
    {
        return in_array($this, [
            self::BOUNCED,
            self::FAILED,
        ], true);
    }
}
