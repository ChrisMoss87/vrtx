<?php

declare(strict_types=1);

namespace App\Domain\Playbook\ValueObjects;

enum TaskInstanceStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case SKIPPED = 'skipped';
    case BLOCKED = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::SKIPPED => 'Skipped',
            self::BLOCKED => 'Blocked',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::SKIPPED => true,
            default => false,
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array($newStatus, [self::IN_PROGRESS, self::BLOCKED, self::SKIPPED]),
            self::IN_PROGRESS => in_array($newStatus, [self::COMPLETED, self::BLOCKED, self::SKIPPED]),
            self::BLOCKED => in_array($newStatus, [self::PENDING, self::IN_PROGRESS, self::SKIPPED]),
            self::COMPLETED, self::SKIPPED => false,
        };
    }
}
