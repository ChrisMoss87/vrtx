<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\ValueObjects\AggregationType;

/**
 * Domain service for handling report aggregations.
 *
 * This service provides logic for validating and building aggregation
 * configurations for reports.
 */
class ReportAggregationService
{
    /**
     * Build SQL aggregation expression.
     */
    public function buildAggregationExpression(
        AggregationType $aggregationType,
        ?string $field,
        string $alias
    ): string {
        // Field validation
        if ($aggregationType->requiresField() && empty($field)) {
            throw new \InvalidArgumentException(
                "Field is required for {$aggregationType->value} aggregation"
            );
        }

        // Build column expression
        $column = $aggregationType === AggregationType::COUNT
            ? '*'
            : "(data->>'{$field}')::numeric";

        // Handle COUNT DISTINCT
        if ($aggregationType === AggregationType::COUNT_DISTINCT) {
            return "COUNT(DISTINCT data->>'{$field}') as \"{$alias}\"";
        }

        $sqlFunction = $aggregationType->sqlFunction();
        return "{$sqlFunction}({$column}) as \"{$alias}\"";
    }

    /**
     * Build date grouping expression for PostgreSQL.
     */
    public function buildDateGroupExpression(
        string $field,
        string $interval,
        string $alias
    ): string {
        $validIntervals = ['hour', 'day', 'week', 'month', 'quarter', 'year'];

        if (!in_array($interval, $validIntervals)) {
            throw new \InvalidArgumentException("Invalid interval: {$interval}");
        }

        return "DATE_TRUNC('{$interval}', {$field}) as \"{$alias}\"";
    }

    /**
     * Build field grouping expression.
     */
    public function buildFieldGroupExpression(string $field, string $alias): string
    {
        // Check if it's a system field
        $systemFields = ['id', 'created_at', 'updated_at', 'module_id'];

        if (in_array($field, $systemFields)) {
            return "{$field} as \"{$alias}\"";
        }

        // JSON field
        return "data->>'{$field}' as \"{$alias}\"";
    }

    /**
     * Validate aggregation configuration.
     *
     * @param array<mixed> $aggregation
     * @return array<string> List of validation errors
     */
    public function validateAggregation(array $aggregation): array
    {
        $errors = [];

        if (!isset($aggregation['function'])) {
            $errors[] = 'Aggregation function is required';
            return $errors;
        }

        try {
            $type = AggregationType::from($aggregation['function']);
        } catch (\ValueError $e) {
            $errors[] = "Invalid aggregation function: {$aggregation['function']}";
            return $errors;
        }

        if ($type->requiresField() && empty($aggregation['field'])) {
            $errors[] = "Field is required for {$type->value} aggregation";
        }

        if ($type->requiresNumericField() && isset($aggregation['field_type'])) {
            $numericTypes = ['number', 'decimal', 'currency', 'percentage'];
            if (!in_array($aggregation['field_type'], $numericTypes)) {
                $errors[] = "{$type->value} requires a numeric field";
            }
        }

        return $errors;
    }

    /**
     * Validate grouping configuration.
     *
     * @param array<mixed> $grouping
     * @return array<string> List of validation errors
     */
    public function validateGrouping(array $grouping): array
    {
        $errors = [];

        foreach ($grouping as $index => $group) {
            if (!isset($group['field']) && !is_string($group)) {
                $errors[] = "Grouping at index {$index} must have a field";
                continue;
            }

            // Validate interval for date fields
            if (isset($group['interval'])) {
                $validIntervals = ['hour', 'day', 'week', 'month', 'quarter', 'year'];
                if (!in_array($group['interval'], $validIntervals)) {
                    $errors[] = "Invalid interval at index {$index}: {$group['interval']}";
                }
            }
        }

        return $errors;
    }

    /**
     * Calculate KPI value with comparison.
     *
     * @return array{value: float, previous_value: float|null, change: float|null, change_percent: float|null, change_type: string|null}
     */
    public function calculateKpiWithComparison(
        float $currentValue,
        ?float $previousValue = null
    ): array {
        $change = null;
        $changePercent = null;
        $changeType = null;

        if ($previousValue !== null) {
            $change = $currentValue - $previousValue;

            if ($previousValue != 0) {
                $changePercent = round(($change / $previousValue) * 100, 2);
            }

            $changeType = $change > 0 ? 'increase' : ($change < 0 ? 'decrease' : 'neutral');
        }

        return [
            'value' => $currentValue,
            'previous_value' => $previousValue,
            'change' => $change,
            'change_percent' => $changePercent,
            'change_type' => $changeType,
        ];
    }

    /**
     * Get default alias for an aggregation.
     */
    public function getDefaultAlias(AggregationType $type, ?string $field): string
    {
        if ($field === null || $field === '*') {
            return $type->value;
        }

        return "{$type->value}_{$field}";
    }
}
