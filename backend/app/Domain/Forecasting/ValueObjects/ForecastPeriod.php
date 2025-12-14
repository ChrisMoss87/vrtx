<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * ForecastPeriod value object.
 *
 * Represents a time period for forecasting with type and start/end dates.
 */
final readonly class ForecastPeriod
{
    public const TYPE_WEEK = 'week';
    public const TYPE_MONTH = 'month';
    public const TYPE_QUARTER = 'quarter';
    public const TYPE_YEAR = 'year';

    private function __construct(
        private string $type,
        private DateTimeImmutable $start,
        private DateTimeImmutable $end,
    ) {
        $this->validate();
    }

    /**
     * Create a period from type and start date.
     */
    public static function fromType(string $type, ?DateTimeImmutable $referenceDate = null): self
    {
        $reference = $referenceDate ?? new DateTimeImmutable();

        [$start, $end] = match ($type) {
            self::TYPE_WEEK => [
                $reference->modify('monday this week'),
                $reference->modify('sunday this week'),
            ],
            self::TYPE_MONTH => [
                new DateTimeImmutable($reference->format('Y-m-01')),
                new DateTimeImmutable($reference->format('Y-m-t')),
            ],
            self::TYPE_QUARTER => self::getQuarterDates($reference),
            self::TYPE_YEAR => [
                new DateTimeImmutable($reference->format('Y-01-01')),
                new DateTimeImmutable($reference->format('Y-12-31')),
            ],
            default => throw new InvalidArgumentException("Invalid period type: {$type}"),
        };

        return new self($type, $start, $end);
    }

    /**
     * Create a period with explicit dates.
     */
    public static function create(
        string $type,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): self {
        return new self($type, $start, $end);
    }

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['type'], $data['start'], $data['end'])) {
            throw new InvalidArgumentException('Period requires type, start, and end');
        }

        return new self(
            type: $data['type'],
            start: $data['start'] instanceof DateTimeImmutable
                ? $data['start']
                : new DateTimeImmutable($data['start']),
            end: $data['end'] instanceof DateTimeImmutable
                ? $data['end']
                : new DateTimeImmutable($data['end']),
        );
    }

    /**
     * Get quarter start and end dates.
     */
    private static function getQuarterDates(DateTimeImmutable $date): array
    {
        $month = (int) $date->format('n');
        $year = (int) $date->format('Y');

        $quarter = (int) ceil($month / 3);
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $startMonth + 2;

        $start = new DateTimeImmutable(sprintf('%d-%02d-01', $year, $startMonth));
        $end = new DateTimeImmutable(sprintf('%d-%02d-%s', $year, $endMonth, $start->modify('+2 months')->format('t')));

        return [$start, $end];
    }

    /**
     * Validate the period.
     */
    private function validate(): void
    {
        if (!in_array($this->type, [self::TYPE_WEEK, self::TYPE_MONTH, self::TYPE_QUARTER, self::TYPE_YEAR], true)) {
            throw new InvalidArgumentException("Invalid period type: {$this->type}");
        }

        if ($this->start > $this->end) {
            throw new InvalidArgumentException('Period start must be before or equal to end');
        }
    }

    /**
     * Check if this period is current.
     */
    public function isCurrent(): bool
    {
        $now = new DateTimeImmutable();
        return $now >= $this->start && $now <= $this->end;
    }

    /**
     * Check if a date is within this period.
     */
    public function contains(DateTimeImmutable $date): bool
    {
        return $date >= $this->start && $date <= $this->end;
    }

    /**
     * Get the number of days in this period.
     */
    public function getDays(): int
    {
        return (int) $this->start->diff($this->end)->days + 1;
    }

    /**
     * Get the next period of the same type.
     */
    public function next(): self
    {
        $nextStart = match ($this->type) {
            self::TYPE_WEEK => $this->start->modify('+1 week'),
            self::TYPE_MONTH => $this->start->modify('+1 month'),
            self::TYPE_QUARTER => $this->start->modify('+3 months'),
            self::TYPE_YEAR => $this->start->modify('+1 year'),
        };

        return self::fromType($this->type, $nextStart);
    }

    /**
     * Get the previous period of the same type.
     */
    public function previous(): self
    {
        $prevStart = match ($this->type) {
            self::TYPE_WEEK => $this->start->modify('-1 week'),
            self::TYPE_MONTH => $this->start->modify('-1 month'),
            self::TYPE_QUARTER => $this->start->modify('-3 months'),
            self::TYPE_YEAR => $this->start->modify('-1 year'),
        };

        return self::fromType($this->type, $prevStart);
    }

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this->type) {
            self::TYPE_WEEK => sprintf('Week of %s', $this->start->format('M j, Y')),
            self::TYPE_MONTH => $this->start->format('F Y'),
            self::TYPE_QUARTER => sprintf('Q%d %s', $this->getQuarterNumber(), $this->start->format('Y')),
            self::TYPE_YEAR => $this->start->format('Y'),
        };
    }

    /**
     * Get quarter number (1-4).
     */
    private function getQuarterNumber(): int
    {
        return (int) ceil((int) $this->start->format('n') / 3);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'start' => $this->start->format('Y-m-d'),
            'end' => $this->end->format('Y-m-d'),
            'label' => $this->label(),
        ];
    }

    // ========== Getters ==========

    public function type(): string
    {
        return $this->type;
    }

    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    public function end(): DateTimeImmutable
    {
        return $this->end;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type
            && $this->start == $other->start
            && $this->end == $other->end;
    }
}
