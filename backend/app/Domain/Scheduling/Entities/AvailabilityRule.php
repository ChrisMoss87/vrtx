<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Entities;

use App\Domain\Scheduling\ValueObjects\DayOfWeek;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use InvalidArgumentException;

/**
 * AvailabilityRule entity.
 *
 * Represents a user's recurring availability for a specific day of the week.
 */
final class AvailabilityRule implements Entity
{
    private function __construct(
        private ?int $id,
        private UserId $userId,
        private DayOfWeek $dayOfWeek,
        private string $startTime,
        private string $endTime,
        private bool $isAvailable,
        private ?Timestamp $createdAt,
        private ?Timestamp $updatedAt,
    ) {}

    /**
     * Create a new availability rule.
     */
    public static function create(
        UserId $userId,
        DayOfWeek $dayOfWeek,
        string $startTime,
        string $endTime,
        bool $isAvailable = true,
    ): self {
        self::validateTimeRange($startTime, $endTime);

        return new self(
            id: null,
            userId: $userId,
            dayOfWeek: $dayOfWeek,
            startTime: $startTime,
            endTime: $endTime,
            isAvailable: $isAvailable,
            createdAt: Timestamp::now(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        UserId $userId,
        DayOfWeek $dayOfWeek,
        string $startTime,
        string $endTime,
        bool $isAvailable,
        ?Timestamp $createdAt,
        ?Timestamp $updatedAt,
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            dayOfWeek: $dayOfWeek,
            startTime: $startTime,
            endTime: $endTime,
            isAvailable: $isAvailable,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Update the time range.
     */
    public function updateTimeRange(string $startTime, string $endTime): void
    {
        self::validateTimeRange($startTime, $endTime);

        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Set availability status.
     */
    public function setAvailable(bool $isAvailable): void
    {
        $this->isAvailable = $isAvailable;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Check if a given time falls within this availability window.
     */
    public function containsTime(string $time): bool
    {
        if (!$this->isAvailable) {
            return false;
        }

        return $time >= $this->startTime && $time < $this->endTime;
    }

    /**
     * Get the duration of this availability window in minutes.
     */
    public function durationMinutes(): int
    {
        $start = strtotime($this->startTime);
        $end = strtotime($this->endTime);
        return (int) (($end - $start) / 60);
    }

    // ========== Validation Methods ==========

    private static function validateTimeRange(string $startTime, string $endTime): void
    {
        if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            throw new InvalidArgumentException('Start time must be in HH:MM format');
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            throw new InvalidArgumentException('End time must be in HH:MM format');
        }

        if ($endTime <= $startTime) {
            throw new InvalidArgumentException('End time must be after start time');
        }
    }

    // ========== Entity Implementation ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function equals(\App\Domain\Shared\Contracts\Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->id !== null && $this->id === $other->id;
    }

    // ========== Getters ==========

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function dayOfWeek(): DayOfWeek
    {
        return $this->dayOfWeek;
    }

    public function startTime(): string
    {
        return $this->startTime;
    }

    public function endTime(): string
    {
        return $this->endTime;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function createdAt(): ?Timestamp
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?Timestamp
    {
        return $this->updatedAt;
    }
}
