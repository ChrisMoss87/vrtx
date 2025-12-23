<?php

declare(strict_types=1);

namespace App\Domain\Goal\ValueObjects;

enum GoalStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case ACHIEVED = 'achieved';
    case MISSED = 'missed';
    case PAUSED = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'In Progress',
            self::ACHIEVED => 'Achieved',
            self::MISSED => 'Missed',
            self::PAUSED => 'Paused',
        };
    }

    public function isInProgress(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    public function isAchieved(): bool
    {
        return $this === self::ACHIEVED;
    }

    public function isMissed(): bool
    {
        return $this === self::MISSED;
    }

    public function isPaused(): bool
    {
        return $this === self::PAUSED;
    }

    public function isActive(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    public function isFinal(): bool
    {
        return $this === self::ACHIEVED || $this === self::MISSED;
    }

    public function canResume(): bool
    {
        return $this === self::PAUSED;
    }

    public function canPause(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    public function canUpdateProgress(): bool
    {
        return $this === self::IN_PROGRESS;
    }
}
