<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Value object representing meeting duration in minutes.
 */
final readonly class MeetingDuration implements JsonSerializable
{
    private const MIN_DURATION = 5;
    private const MAX_DURATION = 480; // 8 hours

    public function __construct(
        private int $minutes
    ) {
        if ($this->minutes < self::MIN_DURATION) {
            throw new InvalidArgumentException(
                sprintf('Meeting duration must be at least %d minutes', self::MIN_DURATION)
            );
        }

        if ($this->minutes > self::MAX_DURATION) {
            throw new InvalidArgumentException(
                sprintf('Meeting duration cannot exceed %d minutes', self::MAX_DURATION)
            );
        }
    }

    /**
     * Create from hours.
     */
    public static function fromHours(float $hours): self
    {
        return new self((int) ($hours * 60));
    }

    /**
     * Get duration in minutes.
     */
    public function minutes(): int
    {
        return $this->minutes;
    }

    /**
     * Get duration in hours.
     */
    public function hours(): float
    {
        return $this->minutes / 60;
    }

    /**
     * Get formatted duration string.
     */
    public function formatted(): string
    {
        if ($this->minutes < 60) {
            return "{$this->minutes} min";
        }

        $hours = floor($this->minutes / 60);
        $remainingMinutes = $this->minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours === 1 ? "1 hour" : "{$hours} hours";
        }

        return sprintf("%d hour%s %d min", $hours, $hours === 1 ? '' : 's', $remainingMinutes);
    }

    /**
     * Check if this is a standard duration (15, 30, 45, 60 minutes, etc.).
     */
    public function isStandard(): bool
    {
        return in_array($this->minutes, [15, 30, 45, 60, 90, 120]);
    }

    /**
     * Add minutes to this duration.
     */
    public function add(int $minutes): self
    {
        return new self($this->minutes + $minutes);
    }

    /**
     * Subtract minutes from this duration.
     */
    public function subtract(int $minutes): self
    {
        return new self($this->minutes - $minutes);
    }

    /**
     * Get common meeting durations.
     *
     * @return array<MeetingDuration>
     */
    public static function commonDurations(): array
    {
        return [
            new self(15),
            new self(30),
            new self(45),
            new self(60),
            new self(90),
            new self(120),
        ];
    }

    public function equals(MeetingDuration $other): bool
    {
        return $this->minutes === $other->minutes;
    }

    public function toInt(): int
    {
        return $this->minutes;
    }

    public function jsonSerialize(): int
    {
        return $this->minutes;
    }

    public function __toString(): string
    {
        return $this->formatted();
    }
}
