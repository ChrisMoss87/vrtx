<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\ValueObjects;

/**
 * Enum representing days of the week.
 */
enum DayOfWeek: int
{
    case SUNDAY = 0;
    case MONDAY = 1;
    case TUESDAY = 2;
    case WEDNESDAY = 3;
    case THURSDAY = 4;
    case FRIDAY = 5;
    case SATURDAY = 6;

    /**
     * Get human-readable label for this day.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUNDAY => 'Sunday',
            self::MONDAY => 'Monday',
            self::TUESDAY => 'Tuesday',
            self::WEDNESDAY => 'Wednesday',
            self::THURSDAY => 'Thursday',
            self::FRIDAY => 'Friday',
            self::SATURDAY => 'Saturday',
        };
    }

    /**
     * Get short label (3 letters).
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::SUNDAY => 'Sun',
            self::MONDAY => 'Mon',
            self::TUESDAY => 'Tue',
            self::WEDNESDAY => 'Wed',
            self::THURSDAY => 'Thu',
            self::FRIDAY => 'Fri',
            self::SATURDAY => 'Sat',
        };
    }

    /**
     * Check if this is a weekend day.
     */
    public function isWeekend(): bool
    {
        return in_array($this, [self::SATURDAY, self::SUNDAY]);
    }

    /**
     * Check if this is a weekday.
     */
    public function isWeekday(): bool
    {
        return !$this->isWeekend();
    }

    /**
     * Get the next day.
     */
    public function next(): self
    {
        return self::from(($this->value + 1) % 7);
    }

    /**
     * Get the previous day.
     */
    public function previous(): self
    {
        return self::from(($this->value + 6) % 7);
    }

    /**
     * Get all weekdays.
     *
     * @return array<DayOfWeek>
     */
    public static function weekdays(): array
    {
        return [
            self::MONDAY,
            self::TUESDAY,
            self::WEDNESDAY,
            self::THURSDAY,
            self::FRIDAY,
        ];
    }

    /**
     * Get all weekend days.
     *
     * @return array<DayOfWeek>
     */
    public static function weekendDays(): array
    {
        return [
            self::SATURDAY,
            self::SUNDAY,
        ];
    }
}
