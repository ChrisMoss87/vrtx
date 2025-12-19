<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Value object representing a point in time.
 *
 * This wraps DateTimeImmutable to provide a consistent timestamp
 * representation across the domain layer with common operations.
 */
final readonly class Timestamp
{
    private function __construct(
        private DateTimeImmutable $value
    ) {}

    public static function now(): self
    {
        return new self(new DateTimeImmutable());
    }

    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        return new self(DateTimeImmutable::createFromInterface($dateTime));
    }

    public static function fromString(string $dateTimeString): self
    {
        $dateTime = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $dateTimeString);

        if ($dateTime === false) {
            // Try common formats
            $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTimeString);
        }

        if ($dateTime === false) {
            $dateTime = DateTimeImmutable::createFromFormat('Y-m-d', $dateTimeString);
        }

        if ($dateTime === false) {
            throw new InvalidArgumentException(
                "Invalid date/time string: {$dateTimeString}. Expected ISO 8601 or Y-m-d H:i:s format."
            );
        }

        return new self($dateTime);
    }

    public static function fromTimestamp(int $timestamp): self
    {
        return new self((new DateTimeImmutable())->setTimestamp($timestamp));
    }

    public function value(): DateTimeImmutable
    {
        return $this->value;
    }

    public function toIso8601(): string
    {
        return $this->value->format(DateTimeInterface::ATOM);
    }

    public function toDateTimeString(): string
    {
        return $this->value->format('Y-m-d H:i:s');
    }

    public function toDateString(): string
    {
        return $this->value->format('Y-m-d');
    }

    public function toUnixTimestamp(): int
    {
        return $this->value->getTimestamp();
    }

    public function isPast(): bool
    {
        return $this->value < new DateTimeImmutable();
    }

    public function isFuture(): bool
    {
        return $this->value > new DateTimeImmutable();
    }

    public function isBefore(Timestamp $other): bool
    {
        return $this->value < $other->value;
    }

    public function isAfter(Timestamp $other): bool
    {
        return $this->value > $other->value;
    }

    public function equals(Timestamp $other): bool
    {
        return $this->value == $other->value;
    }

    public function addDays(int $days): self
    {
        return new self($this->value->modify("+{$days} days"));
    }

    public function addHours(int $hours): self
    {
        return new self($this->value->modify("+{$hours} hours"));
    }

    public function addMinutes(int $minutes): self
    {
        return new self($this->value->modify("+{$minutes} minutes"));
    }

    public function diffInSeconds(Timestamp $other): int
    {
        return abs($this->value->getTimestamp() - $other->value->getTimestamp());
    }

    public function diffInMinutes(Timestamp $other): int
    {
        return (int) floor($this->diffInSeconds($other) / 60);
    }

    public function diffInHours(Timestamp $other): int
    {
        return (int) floor($this->diffInSeconds($other) / 3600);
    }

    public function diffInDays(Timestamp $other): int
    {
        return (int) floor($this->diffInSeconds($other) / 86400);
    }

    public function __toString(): string
    {
        return $this->toIso8601();
    }
}
