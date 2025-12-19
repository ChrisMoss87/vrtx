<?php

declare(strict_types=1);

namespace App\Domain\Reporting\ValueObjects;

/**
 * Enum representing chart types.
 */
enum ChartType: string
{
    case BAR = 'bar';
    case LINE = 'line';
    case PIE = 'pie';
    case DOUGHNUT = 'doughnut';
    case AREA = 'area';
    case FUNNEL = 'funnel';
    case SCATTER = 'scatter';
    case GAUGE = 'gauge';
    case KPI = 'kpi';

    /**
     * Get human-readable label for this chart type.
     */
    public function label(): string
    {
        return match ($this) {
            self::BAR => 'Bar Chart',
            self::LINE => 'Line Chart',
            self::PIE => 'Pie Chart',
            self::DOUGHNUT => 'Doughnut Chart',
            self::AREA => 'Area Chart',
            self::FUNNEL => 'Funnel Chart',
            self::SCATTER => 'Scatter Plot',
            self::GAUGE => 'Gauge',
            self::KPI => 'KPI Card',
        };
    }

    /**
     * Get description for this chart type.
     */
    public function description(): string
    {
        return match ($this) {
            self::BAR => 'Vertical or horizontal bars for comparing values',
            self::LINE => 'Line graph showing trends over time',
            self::PIE => 'Circular chart showing proportions of a whole',
            self::DOUGHNUT => 'Pie chart with a hollow center',
            self::AREA => 'Line chart with filled area underneath',
            self::FUNNEL => 'Funnel visualization for conversion processes',
            self::SCATTER => 'Points plotted on x/y axis to show relationships',
            self::GAUGE => 'Dial or gauge showing a single metric',
            self::KPI => 'Single key performance indicator display',
        };
    }

    /**
     * Get the category for this chart type.
     */
    public function category(): string
    {
        return match ($this) {
            self::BAR, self::LINE, self::AREA => 'comparison',
            self::PIE, self::DOUGHNUT => 'distribution',
            self::FUNNEL => 'process',
            self::SCATTER => 'relationship',
            self::GAUGE, self::KPI => 'indicator',
        };
    }

    /**
     * Check if this chart type requires a time dimension.
     */
    public function preferTimeDimension(): bool
    {
        return match ($this) {
            self::LINE, self::AREA => true,
            default => false,
        };
    }

    /**
     * Get all available chart types as an array.
     *
     * @return array<string, array{label: string, description: string, category: string, prefer_time_dimension: bool}>
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = [
                'label' => $case->label(),
                'description' => $case->description(),
                'category' => $case->category(),
                'prefer_time_dimension' => $case->preferTimeDimension(),
            ];
        }
        return $result;
    }
}
