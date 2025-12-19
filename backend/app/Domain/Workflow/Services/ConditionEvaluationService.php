<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Services;

/**
 * Domain service for evaluating workflow conditions.
 *
 * This service evaluates condition arrays against context data
 * to determine if workflow steps should execute.
 */
class ConditionEvaluationService
{
    /**
     * Supported operators for condition evaluation.
     */
    private const OPERATORS = [
        'equals', 'not_equals', 'contains', 'not_contains',
        'starts_with', 'ends_with', 'greater_than', 'less_than',
        'greater_than_or_equals', 'less_than_or_equals',
        'is_empty', 'is_not_empty', 'in', 'not_in',
        'between', 'regex_match', 'changed', 'changed_to', 'changed_from',
    ];

    /**
     * Evaluate a set of conditions against context data.
     *
     * @param array<array{field?: string, operator?: string, value?: mixed, logic?: string, conditions?: array}> $conditions
     * @param array<string, mixed> $context
     * @return bool True if conditions are met
     */
    public function evaluate(array $conditions, array $context): bool
    {
        if (empty($conditions)) {
            return true;
        }

        // Handle group conditions with logic operator
        if (isset($conditions['logic']) && isset($conditions['conditions'])) {
            return $this->evaluateGroup($conditions, $context);
        }

        // Handle array of conditions (default AND logic)
        $logic = $conditions['logic'] ?? 'and';
        $conditionList = $conditions['conditions'] ?? $conditions;

        // If it's a single condition object
        if (isset($conditions['field']) || isset($conditions['operator'])) {
            return $this->evaluateSingleCondition($conditions, $context);
        }

        // Evaluate list of conditions
        return $this->evaluateConditionList($conditionList, $logic, $context);
    }

    /**
     * Evaluate a group of conditions with a logic operator.
     */
    private function evaluateGroup(array $group, array $context): bool
    {
        $logic = strtolower($group['logic'] ?? 'and');
        $conditions = $group['conditions'] ?? [];

        return $this->evaluateConditionList($conditions, $logic, $context);
    }

    /**
     * Evaluate a list of conditions with specified logic.
     */
    private function evaluateConditionList(array $conditions, string $logic, array $context): bool
    {
        if (empty($conditions)) {
            return true;
        }

        $results = [];

        foreach ($conditions as $condition) {
            if (is_array($condition)) {
                // Check if this is a nested group
                if (isset($condition['logic']) && isset($condition['conditions'])) {
                    $results[] = $this->evaluateGroup($condition, $context);
                } else {
                    $results[] = $this->evaluateSingleCondition($condition, $context);
                }
            }
        }

        if (empty($results)) {
            return true;
        }

        return match (strtolower($logic)) {
            'or' => in_array(true, $results, true),
            'and' => !in_array(false, $results, true),
            default => !in_array(false, $results, true), // Default to AND
        };
    }

    /**
     * Evaluate a single condition.
     */
    private function evaluateSingleCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        if ($field === null) {
            return true;
        }

        // Get the field value from context
        $fieldValue = $this->getFieldValue($field, $context);

        // Evaluate based on operator
        return $this->evaluateOperator($operator, $fieldValue, $value, $context, $field);
    }

    /**
     * Get a field value from the context using dot notation.
     */
    private function getFieldValue(string $field, array $context): mixed
    {
        // Support for dot notation (e.g., "record.status")
        $parts = explode('.', $field);
        $value = $context;

        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Evaluate a specific operator.
     */
    private function evaluateOperator(
        string $operator,
        mixed $fieldValue,
        mixed $conditionValue,
        array $context,
        string $field,
    ): bool {
        return match (strtolower($operator)) {
            'equals', '=', '==' => $this->equals($fieldValue, $conditionValue),
            'not_equals', '!=', '<>' => !$this->equals($fieldValue, $conditionValue),
            'contains' => $this->contains($fieldValue, $conditionValue),
            'not_contains' => !$this->contains($fieldValue, $conditionValue),
            'starts_with' => $this->startsWith($fieldValue, $conditionValue),
            'ends_with' => $this->endsWith($fieldValue, $conditionValue),
            'greater_than', '>' => $this->greaterThan($fieldValue, $conditionValue),
            'less_than', '<' => $this->lessThan($fieldValue, $conditionValue),
            'greater_than_or_equals', '>=' => $this->greaterThanOrEquals($fieldValue, $conditionValue),
            'less_than_or_equals', '<=' => $this->lessThanOrEquals($fieldValue, $conditionValue),
            'is_empty' => $this->isEmpty($fieldValue),
            'is_not_empty' => !$this->isEmpty($fieldValue),
            'in' => $this->in($fieldValue, $conditionValue),
            'not_in' => !$this->in($fieldValue, $conditionValue),
            'between' => $this->between($fieldValue, $conditionValue),
            'regex_match', 'regex' => $this->regexMatch($fieldValue, $conditionValue),
            'changed' => $this->hasChanged($field, $context),
            'changed_to' => $this->changedTo($field, $conditionValue, $context),
            'changed_from' => $this->changedFrom($field, $conditionValue, $context),
            default => true,
        };
    }

    /**
     * Check equality (type-coerced for flexibility).
     */
    private function equals(mixed $fieldValue, mixed $conditionValue): bool
    {
        // Handle null comparison
        if ($fieldValue === null && $conditionValue === null) {
            return true;
        }

        // Handle numeric string comparison
        if (is_numeric($fieldValue) && is_numeric($conditionValue)) {
            return (float) $fieldValue === (float) $conditionValue;
        }

        // Handle boolean conversion
        if (is_bool($conditionValue)) {
            return (bool) $fieldValue === $conditionValue;
        }

        return $fieldValue == $conditionValue;
    }

    /**
     * Check if a string contains another string.
     */
    private function contains(mixed $fieldValue, mixed $conditionValue): bool
    {
        if (!is_string($fieldValue) || !is_string($conditionValue)) {
            // Handle array contains
            if (is_array($fieldValue)) {
                return in_array($conditionValue, $fieldValue);
            }
            return false;
        }

        return str_contains(strtolower($fieldValue), strtolower($conditionValue));
    }

    /**
     * Check if a string starts with another string.
     */
    private function startsWith(mixed $fieldValue, mixed $conditionValue): bool
    {
        if (!is_string($fieldValue) || !is_string($conditionValue)) {
            return false;
        }

        return str_starts_with(strtolower($fieldValue), strtolower($conditionValue));
    }

    /**
     * Check if a string ends with another string.
     */
    private function endsWith(mixed $fieldValue, mixed $conditionValue): bool
    {
        if (!is_string($fieldValue) || !is_string($conditionValue)) {
            return false;
        }

        return str_ends_with(strtolower($fieldValue), strtolower($conditionValue));
    }

    /**
     * Check if a value is greater than another.
     */
    private function greaterThan(mixed $fieldValue, mixed $conditionValue): bool
    {
        if (!is_numeric($fieldValue) || !is_numeric($conditionValue)) {
            return false;
        }

        return (float) $fieldValue > (float) $conditionValue;
    }

    /**
     * Check if a value is less than another.
     */
    private function lessThan(mixed $fieldValue, mixed $conditionValue): bool
    {
        if (!is_numeric($fieldValue) || !is_numeric($conditionValue)) {
            return false;
        }

        return (float) $fieldValue < (float) $conditionValue;
    }

    /**
     * Check if a value is greater than or equal to another.
     */
    private function greaterThanOrEquals(mixed $fieldValue, mixed $conditionValue): bool
    {
        if (!is_numeric($fieldValue) || !is_numeric($conditionValue)) {
            return false;
        }

        return (float) $fieldValue >= (float) $conditionValue;
    }

    /**
     * Check if a value is less than or equal to another.
     */
    private function lessThanOrEquals(mixed $fieldValue, mixed $conditionValue): bool
    {
        if (!is_numeric($fieldValue) || !is_numeric($conditionValue)) {
            return false;
        }

        return (float) $fieldValue <= (float) $conditionValue;
    }

    /**
     * Check if a value is empty.
     */
    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return empty($value);
        }

        return false;
    }

    /**
     * Check if a value is in a list.
     */
    private function in(mixed $fieldValue, mixed $conditionValue): bool
    {
        if (!is_array($conditionValue)) {
            $conditionValue = [$conditionValue];
        }

        return in_array($fieldValue, $conditionValue, false);
    }

    /**
     * Check if a value is between two values (inclusive).
     */
    private function between(mixed $fieldValue, mixed $conditionValue): bool
    {
        if (!is_array($conditionValue) || count($conditionValue) !== 2) {
            return false;
        }

        if (!is_numeric($fieldValue)) {
            return false;
        }

        $min = (float) $conditionValue[0];
        $max = (float) $conditionValue[1];
        $value = (float) $fieldValue;

        return $value >= $min && $value <= $max;
    }

    /**
     * Check if a value matches a regex pattern.
     */
    private function regexMatch(mixed $fieldValue, mixed $conditionValue): bool
    {
        if (!is_string($fieldValue) || !is_string($conditionValue)) {
            return false;
        }

        // Ensure pattern has delimiters
        $pattern = $conditionValue;
        if ($pattern[0] !== '/') {
            $pattern = '/' . $pattern . '/';
        }

        return (bool) @preg_match($pattern, $fieldValue);
    }

    /**
     * Check if a field has changed (from old_data in context).
     */
    private function hasChanged(string $field, array $context): bool
    {
        $changes = $context['changes'] ?? [];
        $changedFields = $context['changed_fields'] ?? [];

        // Check if the field is in the list of changed fields
        $fieldParts = explode('.', $field);
        $fieldName = end($fieldParts);

        // Handle record.field_name format
        if ($fieldParts[0] === 'record' && count($fieldParts) > 1) {
            $fieldName = $fieldParts[1];
        }

        if (in_array($fieldName, $changedFields, true)) {
            return true;
        }

        return isset($changes[$fieldName]);
    }

    /**
     * Check if a field changed to a specific value.
     */
    private function changedTo(string $field, mixed $conditionValue, array $context): bool
    {
        if (!$this->hasChanged($field, $context)) {
            return false;
        }

        $changes = $context['changes'] ?? [];
        $fieldParts = explode('.', $field);
        $fieldName = end($fieldParts);

        if ($fieldParts[0] === 'record' && count($fieldParts) > 1) {
            $fieldName = $fieldParts[1];
        }

        if (isset($changes[$fieldName]['new'])) {
            return $this->equals($changes[$fieldName]['new'], $conditionValue);
        }

        return false;
    }

    /**
     * Check if a field changed from a specific value.
     */
    private function changedFrom(string $field, mixed $conditionValue, array $context): bool
    {
        if (!$this->hasChanged($field, $context)) {
            return false;
        }

        $changes = $context['changes'] ?? [];
        $fieldParts = explode('.', $field);
        $fieldName = end($fieldParts);

        if ($fieldParts[0] === 'record' && count($fieldParts) > 1) {
            $fieldName = $fieldParts[1];
        }

        if (isset($changes[$fieldName]['old'])) {
            return $this->equals($changes[$fieldName]['old'], $conditionValue);
        }

        return false;
    }

    /**
     * Get all available operators.
     */
    public static function getOperators(): array
    {
        return [
            // Basic comparison
            'equals' => 'Equals',
            'not_equals' => 'Does not equal',
            'greater_than' => 'Greater than',
            'greater_than_or_equals' => 'Greater than or equals',
            'less_than' => 'Less than',
            'less_than_or_equals' => 'Less than or equals',

            // String
            'contains' => 'Contains',
            'not_contains' => 'Does not contain',
            'starts_with' => 'Starts with',
            'ends_with' => 'Ends with',
            'regex_match' => 'Matches pattern (regex)',

            // Null/Empty
            'is_empty' => 'Is empty',
            'is_not_empty' => 'Is not empty',

            // List
            'in' => 'Is in list',
            'not_in' => 'Is not in list',
            'between' => 'Is between',

            // Change detection
            'changed' => 'Has changed',
            'changed_to' => 'Changed to',
            'changed_from' => 'Changed from',
        ];
    }

    /**
     * Get operators organized by category with metadata.
     */
    public static function getOperatorsWithMetadata(): array
    {
        return [
            'comparison' => [
                'label' => 'Comparison',
                'operators' => [
                    'equals' => ['label' => 'Equals', 'requires_value' => true, 'field_types' => ['*']],
                    'not_equals' => ['label' => 'Does not equal', 'requires_value' => true, 'field_types' => ['*']],
                    'greater_than' => ['label' => 'Greater than', 'requires_value' => true, 'field_types' => ['number', 'integer', 'decimal', 'currency', 'percent']],
                    'greater_than_or_equals' => ['label' => 'Greater than or equals', 'requires_value' => true, 'field_types' => ['number', 'integer', 'decimal', 'currency', 'percent']],
                    'less_than' => ['label' => 'Less than', 'requires_value' => true, 'field_types' => ['number', 'integer', 'decimal', 'currency', 'percent']],
                    'less_than_or_equals' => ['label' => 'Less than or equals', 'requires_value' => true, 'field_types' => ['number', 'integer', 'decimal', 'currency', 'percent']],
                ],
            ],
            'string' => [
                'label' => 'Text',
                'operators' => [
                    'contains' => ['label' => 'Contains', 'requires_value' => true, 'field_types' => ['text', 'string', 'textarea', 'email', 'url', 'phone']],
                    'not_contains' => ['label' => 'Does not contain', 'requires_value' => true, 'field_types' => ['text', 'string', 'textarea', 'email', 'url', 'phone']],
                    'starts_with' => ['label' => 'Starts with', 'requires_value' => true, 'field_types' => ['text', 'string', 'textarea', 'email', 'url', 'phone']],
                    'ends_with' => ['label' => 'Ends with', 'requires_value' => true, 'field_types' => ['text', 'string', 'textarea', 'email', 'url', 'phone']],
                    'regex_match' => ['label' => 'Matches pattern (regex)', 'requires_value' => true, 'field_types' => ['text', 'string', 'textarea']],
                ],
            ],
            'null_check' => [
                'label' => 'Empty/Null',
                'operators' => [
                    'is_empty' => ['label' => 'Is empty', 'requires_value' => false, 'field_types' => ['*']],
                    'is_not_empty' => ['label' => 'Is not empty', 'requires_value' => false, 'field_types' => ['*']],
                ],
            ],
            'list' => [
                'label' => 'List/Array',
                'operators' => [
                    'in' => ['label' => 'Is in list', 'requires_value' => true, 'value_type' => 'array', 'field_types' => ['*']],
                    'not_in' => ['label' => 'Is not in list', 'requires_value' => true, 'value_type' => 'array', 'field_types' => ['*']],
                    'between' => ['label' => 'Is between', 'requires_value' => true, 'value_type' => 'range', 'field_types' => ['number', 'integer', 'decimal', 'currency', 'percent']],
                ],
            ],
            'change' => [
                'label' => 'Change Detection',
                'operators' => [
                    'changed' => ['label' => 'Has changed', 'requires_value' => false, 'field_types' => ['*'], 'context' => 'update'],
                    'changed_to' => ['label' => 'Changed to', 'requires_value' => true, 'field_types' => ['*'], 'context' => 'update'],
                    'changed_from' => ['label' => 'Changed from', 'requires_value' => true, 'field_types' => ['*'], 'context' => 'update'],
                ],
            ],
        ];
    }

    /**
     * Get operators available for a specific field type.
     */
    public static function getOperatorsForFieldType(string $fieldType): array
    {
        $allOperators = self::getOperatorsWithMetadata();
        $result = [];

        foreach ($allOperators as $category => $data) {
            $operators = [];
            foreach ($data['operators'] as $key => $meta) {
                $fieldTypes = $meta['field_types'] ?? ['*'];
                if (in_array('*', $fieldTypes) || in_array($fieldType, $fieldTypes)) {
                    $operators[$key] = $meta['label'];
                }
            }
            if (!empty($operators)) {
                $result[$category] = [
                    'label' => $data['label'],
                    'operators' => $operators,
                ];
            }
        }

        return $result;
    }

    /**
     * Get value types supported.
     */
    public static function getValueTypes(): array
    {
        return [
            'static' => 'Static value',
            'field' => 'Another field value',
            'current_user' => 'Current user',
            'current_date' => 'Current date',
            'current_datetime' => 'Current date and time',
        ];
    }
}
