<?php

declare(strict_types=1);

namespace App\Domain\Workflow\ValueObjects;

/**
 * Enum representing workflow execution statuses.
 */
enum ExecutionStatus: string
{
    case PENDING = 'pending';
    case QUEUED = 'queued';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    /**
     * Get human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::QUEUED => 'Queued',
            self::RUNNING => 'Running',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Check if this status indicates the execution is still in progress.
     */
    public function isInProgress(): bool
    {
        return in_array($this, [self::PENDING, self::QUEUED, self::RUNNING]);
    }

    /**
     * Check if this status indicates the execution has finished.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED]);
    }

    /**
     * Check if this status indicates success.
     */
    public function isSuccess(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Check if this status indicates failure.
     */
    public function isFailure(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::QUEUED => 'blue',
            self::RUNNING => 'yellow',
            self::COMPLETED => 'green',
            self::FAILED => 'red',
            self::CANCELLED => 'gray',
        };
    }

    /**
     * Check if this status can transition to the given status.
     */
    public function canTransitionTo(ExecutionStatus $target): bool
    {
        return match ($this) {
            self::PENDING => in_array($target, [self::QUEUED, self::RUNNING, self::CANCELLED]),
            self::QUEUED => in_array($target, [self::RUNNING, self::CANCELLED]),
            self::RUNNING => in_array($target, [self::COMPLETED, self::FAILED, self::CANCELLED]),
            self::COMPLETED, self::FAILED, self::CANCELLED => false, // Terminal states
        };
    }
}
