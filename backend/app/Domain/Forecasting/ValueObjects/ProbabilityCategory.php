<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\ValueObjects;

/**
 * ProbabilityCategory enum.
 *
 * Categorizes deals in a forecast based on their likelihood to close.
 */
enum ProbabilityCategory: string
{
    case OMITTED = 'omitted';
    case PIPELINE = 'pipeline';
    case BEST_CASE = 'best_case';
    case COMMIT = 'commit';
    case CLOSED = 'closed';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::OMITTED => 'Omitted',
            self::PIPELINE => 'Pipeline',
            self::BEST_CASE => 'Best Case',
            self::COMMIT => 'Commit',
            self::CLOSED => 'Closed',
        };
    }

    /**
     * Get description.
     */
    public function description(): string
    {
        return match ($this) {
            self::OMITTED => 'Excluded from forecast calculations',
            self::PIPELINE => 'Standard pipeline deals with no special forecast status',
            self::BEST_CASE => 'Optimistic forecast - may close this period',
            self::COMMIT => 'High confidence deals - committed to close this period',
            self::CLOSED => 'Already closed and won',
        };
    }

    /**
     * Get confidence level (0-100).
     */
    public function confidenceLevel(): int
    {
        return match ($this) {
            self::OMITTED => 0,
            self::PIPELINE => 50,
            self::BEST_CASE => 75,
            self::COMMIT => 90,
            self::CLOSED => 100,
        };
    }

    /**
     * Check if this category should be included in forecast totals.
     */
    public function isIncludedInForecast(): bool
    {
        return $this !== self::OMITTED;
    }

    /**
     * Get sort order for display.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::CLOSED => 1,
            self::COMMIT => 2,
            self::BEST_CASE => 3,
            self::PIPELINE => 4,
            self::OMITTED => 5,
        };
    }

    /**
     * Get display color.
     */
    public function color(): string
    {
        return match ($this) {
            self::OMITTED => 'gray',
            self::PIPELINE => 'blue',
            self::BEST_CASE => 'yellow',
            self::COMMIT => 'green',
            self::CLOSED => 'emerald',
        };
    }

    /**
     * Get all categories as array.
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = [
                'label' => $case->label(),
                'description' => $case->description(),
                'confidence' => $case->confidenceLevel(),
                'color' => $case->color(),
                'included_in_forecast' => $case->isIncludedInForecast(),
            ];
        }
        return $result;
    }
}
