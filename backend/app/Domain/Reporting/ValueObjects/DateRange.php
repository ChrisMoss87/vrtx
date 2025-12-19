<?php

declare(strict_types=1);

namespace App\Domain\Reporting\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Value Object representing a date range for reports.
 */
final readonly class DateRange implements JsonSerializable
{
    private function __construct(
        private string $field,
        private ?string $type,
        private ?DateTimeImmutable $start,
        private ?DateTimeImmutable $end,
    ) {
        $this->validate();
    }

    /**
     * Create a date range with a predefined type.
     */
    public static function fromType(string $field, string $type): self
    {
        return new self(
            field: $field,
            type: $type,
            start: null,
            end: null,
        );
    }

    /**
     * Create a custom date range with start and end dates.
     */
    public static function custom(string $field, DateTimeImmutable $start, DateTimeImmutable $end): self
    {
        return new self(
            field: $field,
            type: null,
            start: $start,
            end: $end,
        );
    }

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        $field = $data['field'] ?? 'created_at';
        $type = $data['type'] ?? null;
        $start = isset($data['start']) ? new DateTimeImmutable($data['start']) : null;
        $end = isset($data['end']) ? new DateTimeImmutable($data['end']) : null;

        return new self(
            field: $field,
            type: $type,
            start: $start,
            end: $end,
        );
    }

    /**
     * Validate the date range.
     */
    private function validate(): void
    {
        if (empty($this->field)) {
            throw new InvalidArgumentException('Date range field cannot be empty');
        }

        if ($this->type === null && $this->start === null && $this->end === null) {
            throw new InvalidArgumentException('Date range must have either a type or start/end dates');
        }

        if ($this->start !== null && $this->end !== null && $this->start > $this->end) {
            throw new InvalidArgumentException('Start date must be before or equal to end date');
        }
    }

    /**
     * Get the calculated start and end dates based on type.
     *
     * @return array{start: DateTimeImmutable|null, end: DateTimeImmutable|null}
     */
    public function getCalculatedDates(): array
    {
        if ($this->type === null) {
            return ['start' => $this->start, 'end' => $this->end];
        }

        $now = new DateTimeImmutable();

        return match ($this->type) {
            'today' => [
                'start' => $now->setTime(0, 0, 0),
                'end' => $now->setTime(23, 59, 59),
            ],
            'yesterday' => [
                'start' => $now->modify('-1 day')->setTime(0, 0, 0),
                'end' => $now->modify('-1 day')->setTime(23, 59, 59),
            ],
            'this_week' => [
                'start' => $now->modify('monday this week')->setTime(0, 0, 0),
                'end' => $now->modify('sunday this week')->setTime(23, 59, 59),
            ],
            'last_week' => [
                'start' => $now->modify('monday last week')->setTime(0, 0, 0),
                'end' => $now->modify('sunday last week')->setTime(23, 59, 59),
            ],
            'this_month' => [
                'start' => $now->modify('first day of this month')->setTime(0, 0, 0),
                'end' => $now->modify('last day of this month')->setTime(23, 59, 59),
            ],
            'last_month' => [
                'start' => $now->modify('first day of last month')->setTime(0, 0, 0),
                'end' => $now->modify('last day of last month')->setTime(23, 59, 59),
            ],
            'this_quarter' => [
                'start' => $this->getQuarterStart($now),
                'end' => $this->getQuarterEnd($now),
            ],
            'last_quarter' => [
                'start' => $this->getQuarterStart($now->modify('-3 months')),
                'end' => $this->getQuarterEnd($now->modify('-3 months')),
            ],
            'this_year' => [
                'start' => $now->modify('first day of january this year')->setTime(0, 0, 0),
                'end' => $now->modify('last day of december this year')->setTime(23, 59, 59),
            ],
            'last_year' => [
                'start' => $now->modify('first day of january last year')->setTime(0, 0, 0),
                'end' => $now->modify('last day of december last year')->setTime(23, 59, 59),
            ],
            'last_7_days' => [
                'start' => $now->modify('-7 days')->setTime(0, 0, 0),
                'end' => $now->setTime(23, 59, 59),
            ],
            'last_30_days' => [
                'start' => $now->modify('-30 days')->setTime(0, 0, 0),
                'end' => $now->setTime(23, 59, 59),
            ],
            'last_90_days' => [
                'start' => $now->modify('-90 days')->setTime(0, 0, 0),
                'end' => $now->setTime(23, 59, 59),
            ],
            default => ['start' => null, 'end' => null],
        };
    }

    /**
     * Get the start of the quarter for a given date.
     */
    private function getQuarterStart(DateTimeImmutable $date): DateTimeImmutable
    {
        $month = (int) $date->format('n');
        $quarterStartMonth = (int) (floor(($month - 1) / 3) * 3 + 1);
        return $date->setDate((int) $date->format('Y'), $quarterStartMonth, 1)->setTime(0, 0, 0);
    }

    /**
     * Get the end of the quarter for a given date.
     */
    private function getQuarterEnd(DateTimeImmutable $date): DateTimeImmutable
    {
        $start = $this->getQuarterStart($date);
        return $start->modify('+3 months -1 day')->setTime(23, 59, 59);
    }

    public function field(): string
    {
        return $this->field;
    }

    public function type(): ?string
    {
        return $this->type;
    }

    public function start(): ?DateTimeImmutable
    {
        return $this->start;
    }

    public function end(): ?DateTimeImmutable
    {
        return $this->end;
    }

    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'type' => $this->type,
            'start' => $this->start?->format('Y-m-d H:i:s'),
            'end' => $this->end?->format('Y-m-d H:i:s'),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
