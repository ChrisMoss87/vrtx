<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Value object representing a time slot for scheduling.
 */
final readonly class TimeSlot implements JsonSerializable
{
    public function __construct(
        private DateTimeImmutable $start,
        private DateTimeImmutable $end,
        private ?DateTimeImmutable $bufferStart = null,
        private ?DateTimeImmutable $bufferEnd = null,
    ) {
        if ($this->end <= $this->start) {
            throw new InvalidArgumentException('End time must be after start time');
        }

        if ($this->bufferStart !== null && $this->bufferStart > $this->start) {
            throw new InvalidArgumentException('Buffer start must be before or equal to start time');
        }

        if ($this->bufferEnd !== null && $this->bufferEnd < $this->end) {
            throw new InvalidArgumentException('Buffer end must be after or equal to end time');
        }
    }

    /**
     * Create a time slot with buffer times.
     */
    public static function withBuffer(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        int $bufferBeforeMinutes,
        int $bufferAfterMinutes
    ): self {
        return new self(
            start: $start,
            end: $end,
            bufferStart: $start->modify("-{$bufferBeforeMinutes} minutes"),
            bufferEnd: $end->modify("+{$bufferAfterMinutes} minutes"),
        );
    }

    /**
     * Get the start time.
     */
    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    /**
     * Get the end time.
     */
    public function end(): DateTimeImmutable
    {
        return $this->end;
    }

    /**
     * Get the buffer start time.
     */
    public function bufferStart(): ?DateTimeImmutable
    {
        return $this->bufferStart ?? $this->start;
    }

    /**
     * Get the buffer end time.
     */
    public function bufferEnd(): ?DateTimeImmutable
    {
        return $this->bufferEnd ?? $this->end;
    }

    /**
     * Get duration in minutes.
     */
    public function durationMinutes(): int
    {
        $diff = $this->end->getTimestamp() - $this->start->getTimestamp();
        return (int) ($diff / 60);
    }

    /**
     * Get total blocked time including buffers in minutes.
     */
    public function totalBlockedMinutes(): int
    {
        $start = $this->bufferStart ?? $this->start;
        $end = $this->bufferEnd ?? $this->end;
        $diff = $end->getTimestamp() - $start->getTimestamp();
        return (int) ($diff / 60);
    }

    /**
     * Check if this slot overlaps with another slot.
     */
    public function overlaps(TimeSlot $other, bool $includeBuffer = true): bool
    {
        if ($includeBuffer) {
            $thisStart = $this->bufferStart();
            $thisEnd = $this->bufferEnd();
            $otherStart = $other->bufferStart();
            $otherEnd = $other->bufferEnd();
        } else {
            $thisStart = $this->start;
            $thisEnd = $this->end;
            $otherStart = $other->start;
            $otherEnd = $other->end;
        }

        return $thisStart < $otherEnd && $thisEnd > $otherStart;
    }

    /**
     * Check if a given time falls within this slot.
     */
    public function contains(DateTimeImmutable $time, bool $includeBuffer = false): bool
    {
        if ($includeBuffer) {
            return $time >= $this->bufferStart() && $time <= $this->bufferEnd();
        }

        return $time >= $this->start && $time <= $this->end;
    }

    /**
     * Format for display in a specific timezone.
     */
    public function formatForTimezone(string $timezone): array
    {
        $start = $this->start->setTimezone(new \DateTimeZone($timezone));
        $end = $this->end->setTimezone(new \DateTimeZone($timezone));

        return [
            'start' => $start->format('c'),
            'end' => $end->format('c'),
            'start_formatted' => $start->format('g:i A'),
            'end_formatted' => $end->format('g:i A'),
        ];
    }

    /**
     * Check if this slot is in the past.
     */
    public function isPast(): bool
    {
        return $this->start < new DateTimeImmutable();
    }

    /**
     * Check if this slot is in the future.
     */
    public function isFuture(): bool
    {
        return $this->start > new DateTimeImmutable();
    }

    public function equals(TimeSlot $other): bool
    {
        return $this->start == $other->start
            && $this->end == $other->end
            && $this->bufferStart == $other->bufferStart
            && $this->bufferEnd == $other->bufferEnd;
    }

    public function toArray(): array
    {
        return [
            'start' => $this->start->format('c'),
            'end' => $this->end->format('c'),
            'buffer_start' => $this->bufferStart?->format('c'),
            'buffer_end' => $this->bufferEnd?->format('c'),
            'duration_minutes' => $this->durationMinutes(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
