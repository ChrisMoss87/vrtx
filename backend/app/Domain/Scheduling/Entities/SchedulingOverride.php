<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Entities;

use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * SchedulingOverride entity.
 *
 * Represents a date-specific override of a user's regular availability.
 * Can be used to block a day or set custom hours.
 */
final class SchedulingOverride implements Entity
{
    private function __construct(
        private ?int $id,
        private UserId $userId,
        private DateTimeImmutable $date,
        private bool $isAvailable,
        private ?string $startTime,
        private ?string $endTime,
        private ?string $reason,
        private ?Timestamp $createdAt,
        private ?Timestamp $updatedAt,
    ) {}

    /**
     * Create a day-off override (completely unavailable).
     */
    public static function createDayOff(
        UserId $userId,
        DateTimeImmutable $date,
        ?string $reason = null,
    ): self {
        return new self(
            id: null,
            userId: $userId,
            date: $date,
            isAvailable: false,
            startTime: null,
            endTime: null,
            reason: $reason,
            createdAt: Timestamp::now(),
            updatedAt: null,
        );
    }

    /**
     * Create a custom hours override.
     */
    public static function createCustomHours(
        UserId $userId,
        DateTimeImmutable $date,
        string $startTime,
        string $endTime,
        ?string $reason = null,
    ): self {
        self::validateTimeRange($startTime, $endTime);

        return new self(
            id: null,
            userId: $userId,
            date: $date,
            isAvailable: true,
            startTime: $startTime,
            endTime: $endTime,
            reason: $reason,
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
        DateTimeImmutable $date,
        bool $isAvailable,
        ?string $startTime,
        ?string $endTime,
        ?string $reason,
        ?Timestamp $createdAt,
        ?Timestamp $updatedAt,
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            date: $date,
            isAvailable: $isAvailable,
            startTime: $startTime,
            endTime: $endTime,
            reason: $reason,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Update custom hours.
     */
    public function updateCustomHours(string $startTime, string $endTime): void
    {
        if (!$this->isAvailable) {
            throw new InvalidArgumentException('Cannot set custom hours for a day-off override');
        }

        self::validateTimeRange($startTime, $endTime);

        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Update reason.
     */
    public function updateReason(?string $reason): void
    {
        $this->reason = $reason;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Check if this is a day-off override.
     */
    public function isDayOff(): bool
    {
        return !$this->isAvailable && $this->startTime === null && $this->endTime === null;
    }

    /**
     * Check if this is a custom hours override.
     */
    public function isCustomHours(): bool
    {
        return $this->isAvailable && $this->startTime !== null && $this->endTime !== null;
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

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function startTime(): ?string
    {
        return $this->startTime;
    }

    public function endTime(): ?string
    {
        return $this->endTime;
    }

    public function reason(): ?string
    {
        return $this->reason;
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
