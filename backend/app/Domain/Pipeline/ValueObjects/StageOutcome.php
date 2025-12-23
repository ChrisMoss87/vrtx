<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\ValueObjects;

/**
 * Value Object representing the outcome type of a pipeline stage.
 */
enum StageOutcome: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    /**
     * Get the display label for this outcome.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    /**
     * Get the color for this outcome.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::Won => 'green',
            self::Lost => 'red',
        };
    }

    /**
     * Get the icon for this outcome.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Open => 'circle',
            self::Won => 'trophy',
            self::Lost => 'x-circle',
        };
    }

    /**
     * Check if this is a terminal (closed) outcome.
     */
    public function isTerminal(): bool
    {
        return $this !== self::Open;
    }

    /**
     * Check if this is a positive outcome.
     */
    public function isPositive(): bool
    {
        return $this === self::Won;
    }

    /**
     * Check if this is a negative outcome.
     */
    public function isNegative(): bool
    {
        return $this === self::Lost;
    }

    /**
     * Get all outcomes as an associative array.
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }
        return $result;
    }
}
