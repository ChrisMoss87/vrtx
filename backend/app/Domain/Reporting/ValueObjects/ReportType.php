<?php

declare(strict_types=1);

namespace App\Domain\Reporting\ValueObjects;

/**
 * Enum representing report types.
 */
enum ReportType: string
{
    case TABLE = 'table';
    case CHART = 'chart';
    case SUMMARY = 'summary';
    case MATRIX = 'matrix';
    case PIVOT = 'pivot';

    /**
     * Get human-readable label for this report type.
     */
    public function label(): string
    {
        return match ($this) {
            self::TABLE => 'Table Report',
            self::CHART => 'Chart Report',
            self::SUMMARY => 'Summary Report',
            self::MATRIX => 'Matrix Report',
            self::PIVOT => 'Pivot Table',
        };
    }

    /**
     * Get description for this report type.
     */
    public function description(): string
    {
        return match ($this) {
            self::TABLE => 'Displays data in a tabular format with rows and columns',
            self::CHART => 'Visualizes data using various chart types',
            self::SUMMARY => 'Shows aggregated summary statistics',
            self::MATRIX => 'Two-dimensional grouping with row and column fields',
            self::PIVOT => 'Advanced pivot table with multiple aggregations',
        };
    }

    /**
     * Check if this report type supports grouping.
     */
    public function supportsGrouping(): bool
    {
        return match ($this) {
            self::CHART, self::SUMMARY, self::MATRIX, self::PIVOT => true,
            self::TABLE => false,
        };
    }

    /**
     * Check if this report type requires aggregations.
     */
    public function requiresAggregations(): bool
    {
        return match ($this) {
            self::CHART, self::SUMMARY, self::MATRIX, self::PIVOT => true,
            self::TABLE => false,
        };
    }

    /**
     * Get all available report types as an array.
     *
     * @return array<string, array{label: string, description: string, supports_grouping: bool, requires_aggregations: bool}>
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = [
                'label' => $case->label(),
                'description' => $case->description(),
                'supports_grouping' => $case->supportsGrouping(),
                'requires_aggregations' => $case->requiresAggregations(),
            ];
        }
        return $result;
    }
}
