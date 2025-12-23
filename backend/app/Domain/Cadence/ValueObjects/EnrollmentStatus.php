<?php

declare(strict_types=1);

namespace App\Domain\Cadence\ValueObjects;

enum EnrollmentStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case REPLIED = 'replied';
    case BOUNCED = 'bounced';
    case UNSUBSCRIBED = 'unsubscribed';
    case MEETING_BOOKED = 'meeting_booked';
    case MANUALLY_REMOVED = 'manually_removed';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
            self::COMPLETED => 'Completed',
            self::REPLIED => 'Replied',
            self::BOUNCED => 'Bounced',
            self::UNSUBSCRIBED => 'Unsubscribed',
            self::MEETING_BOOKED => 'Meeting Booked',
            self::MANUALLY_REMOVED => 'Manually Removed',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canPause(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canResume(): bool
    {
        return $this === self::PAUSED;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::REPLIED,
            self::BOUNCED,
            self::UNSUBSCRIBED,
            self::MEETING_BOOKED,
            self::MANUALLY_REMOVED,
        ], true);
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::REPLIED,
            self::MEETING_BOOKED,
        ], true);
    }
}
