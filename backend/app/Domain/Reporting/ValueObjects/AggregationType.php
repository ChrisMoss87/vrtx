<?php

declare(strict_types=1);

namespace App\Domain\Reporting\ValueObjects;

/**
 * Enum representing aggregation types.
 */
enum AggregationType: string
{
    case COUNT = 'count';
    case COUNT_DISTINCT = 'count_distinct';
    case SUM = 'sum';
    case AVG = 'avg';
    case MIN = 'min';
    case MAX = 'max';

    /**
     * Get human-readable label for this aggregation type.
     */
    public function label(): string
    {
        return match ($this) {
            self::COUNT => 'Count',
            self::COUNT_DISTINCT => 'Count Distinct',
            self::SUM => 'Sum',
            self::AVG => 'Average',
            self::MIN => 'Minimum',
            self::MAX => 'Maximum',
        };
    }

    /**
     * Get description for this aggregation type.
     */
    public function description(): string
    {
        return match ($this) {
            self::COUNT => 'Count the number of records',
            self::COUNT_DISTINCT => 'Count the number of unique values',
            self::SUM => 'Add up all values',
            self::AVG => 'Calculate the average (mean) value',
            self::MIN => 'Find the smallest value',
            self::MAX => 'Find the largest value',
        };
    }

    /**
     * Check if this aggregation requires a field.
     */
    public function requiresField(): bool
    {
        return match ($this) {
            self::COUNT => false,
            self::COUNT_DISTINCT, self::SUM, self::AVG, self::MIN, self::MAX => true,
        };
    }

    /**
     * Check if this aggregation requires a numeric field.
     */
    public function requiresNumericField(): bool
    {
        return match ($this) {
            self::SUM, self::AVG, self::MIN, self::MAX => true,
            self::COUNT, self::COUNT_DISTINCT => false,
        };
    }

    /**
     * Get SQL aggregate function name.
     */
    public function sqlFunction(): string
    {
        return match ($this) {
            self::COUNT => 'COUNT',
            self::COUNT_DISTINCT => 'COUNT',
            self::SUM => 'SUM',
            self::AVG => 'AVG',
            self::MIN => 'MIN',
            self::MAX => 'MAX',
        };
    }

    /**
     * Get all available aggregation types as an array.
     *
     * @return array<string, array{label: string, description: string, requires_field: bool, requires_numeric_field: bool}>
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = [
                'label' => $case->label(),
                'description' => $case->description(),
                'requires_field' => $case->requiresField(),
                'requires_numeric_field' => $case->requiresNumericField(),
            ];
        }
        return $result;
    }
}
