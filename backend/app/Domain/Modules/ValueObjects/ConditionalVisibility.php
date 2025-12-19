<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use JsonSerializable;

/**
 * Represents conditional visibility rules for fields.
 *
 * Controls when a field should be shown or hidden based on the values of other fields.
 * Supports multiple conditions combined with AND/OR operators.
 */
final readonly class ConditionalVisibility implements JsonSerializable
{
    /**
     * @param bool $enabled Whether conditional visibility is active
     * @param string $operator Logical operator: 'and' or 'or'
     * @param array<Condition> $conditions Array of conditions to evaluate
     */
    public function __construct(
        public bool $enabled,
        public string $operator,
        public array $conditions,
    ) {
        $this->validateOperator();
    }

    /**
     * Create a ConditionalVisibility with visibility disabled.
     */
    public static function disabled(): self
    {
        return new self(
            enabled: false,
            operator: 'and',
            conditions: [],
        );
    }

    /**
     * Create from array data (from database or API).
     */
    public static function fromArray(array $data): self
    {
        if (empty($data)) {
            return self::disabled();
        }

        $conditions = array_map(
            fn (array $conditionData): Condition => Condition::fromArray($conditionData),
            $data['conditions'] ?? []
        );

        return new self(
            enabled: $data['enabled'] ?? false,
            operator: $data['operator'] ?? 'and',
            conditions: $conditions,
        );
    }

    /**
     * Check if visibility rules are active.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && count($this->conditions) > 0;
    }

    /**
     * Evaluate all conditions against provided form data.
     *
     * @param array<string, mixed> $formData The current form field values
     * @return bool True if field should be visible
     */
    public function evaluate(array $formData): bool
    {
        if (!$this->isEnabled()) {
            return true; // Always visible if disabled
        }

        if (empty($this->conditions)) {
            return true; // No conditions means always visible
        }

        $results = array_map(
            fn (Condition $condition): bool => $condition->evaluate($formData),
            $this->conditions
        );

        return match ($this->operator) {
            'and' => !in_array(false, $results, true),
            'or' => in_array(true, $results, true),
            default => true,
        };
    }

    /**
     * Get all field names that this visibility depends on.
     *
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return array_unique(
            array_map(
                fn (Condition $condition): string => $condition->field,
                $this->conditions
            )
        );
    }

    /**
     * Validate the operator is one of the allowed values.
     *
     * @throws \InvalidArgumentException
     */
    private function validateOperator(): void
    {
        if (!in_array($this->operator, ['and', 'or'], true)) {
            throw new \InvalidArgumentException(
                "Invalid operator '{$this->operator}'. Must be 'and' or 'or'."
            );
        }
    }

    public function jsonSerialize(): array
    {
        if (!$this->enabled) {
            return ['enabled' => false];
        }

        return [
            'enabled' => $this->enabled,
            'operator' => $this->operator,
            'conditions' => array_map(
                fn (Condition $condition): array => $condition->jsonSerialize(),
                $this->conditions
            ),
        ];
    }
}

/**
 * Represents a single condition in a conditional visibility rule.
 */
final readonly class Condition implements JsonSerializable
{
    /**
     * @param string $field The field API name to check
     * @param string $operator The comparison operator
     * @param mixed $value The value to compare against (optional for some operators)
     * @param string|null $fieldValue Compare against another field's value (optional)
     */
    public function __construct(
        public string $field,
        public string $operator,
        public mixed $value = null,
        public ?string $fieldValue = null,
    ) {
        $this->validateOperator();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            field: $data['field'] ?? '',
            operator: $data['operator'] ?? 'equals',
            value: $data['value'] ?? null,
            fieldValue: $data['field_value'] ?? null,
        );
    }

    /**
     * Evaluate this condition against form data.
     *
     * @param array<string, mixed> $formData
     * @return bool
     */
    public function evaluate(array $formData): bool
    {
        $fieldValue = $formData[$this->field] ?? null;
        $compareValue = $this->fieldValue !== null
            ? ($formData[$this->fieldValue] ?? null)
            : $this->value;

        return match ($this->operator) {
            'equals' => $fieldValue == $compareValue,
            'not_equals' => $fieldValue != $compareValue,
            'contains' => is_string($fieldValue) && is_string($compareValue) && str_contains($fieldValue, $compareValue),
            'not_contains' => is_string($fieldValue) && is_string($compareValue) && !str_contains($fieldValue, $compareValue),
            'starts_with' => is_string($fieldValue) && is_string($compareValue) && str_starts_with($fieldValue, $compareValue),
            'ends_with' => is_string($fieldValue) && is_string($compareValue) && str_ends_with($fieldValue, $compareValue),
            'greater_than' => is_numeric($fieldValue) && is_numeric($compareValue) && $fieldValue > $compareValue,
            'less_than' => is_numeric($fieldValue) && is_numeric($compareValue) && $fieldValue < $compareValue,
            'greater_than_or_equal' => is_numeric($fieldValue) && is_numeric($compareValue) && $fieldValue >= $compareValue,
            'less_than_or_equal' => is_numeric($fieldValue) && is_numeric($compareValue) && $fieldValue <= $compareValue,
            'between' => $this->evaluateBetween($fieldValue, $compareValue),
            'in' => is_array($compareValue) && in_array($fieldValue, $compareValue, true),
            'not_in' => is_array($compareValue) && !in_array($fieldValue, $compareValue, true),
            'is_empty' => empty($fieldValue),
            'is_not_empty' => !empty($fieldValue),
            'is_checked' => $fieldValue === true || $fieldValue === 1 || $fieldValue === '1',
            'is_not_checked' => !($fieldValue === true || $fieldValue === 1 || $fieldValue === '1'),
            default => false,
        };
    }

    /**
     * Evaluate between operator (expects array with min/max).
     */
    private function evaluateBetween(mixed $fieldValue, mixed $compareValue): bool
    {
        if (!is_numeric($fieldValue) || !is_array($compareValue)) {
            return false;
        }

        $min = $compareValue['min'] ?? $compareValue[0] ?? null;
        $max = $compareValue['max'] ?? $compareValue[1] ?? null;

        if (!is_numeric($min) || !is_numeric($max)) {
            return false;
        }

        return $fieldValue >= $min && $fieldValue <= $max;
    }

    /**
     * Validate the operator is supported.
     */
    private function validateOperator(): void
    {
        $validOperators = [
            'equals', 'not_equals',
            'contains', 'not_contains',
            'starts_with', 'ends_with',
            'greater_than', 'less_than',
            'greater_than_or_equal', 'less_than_or_equal',
            'between', 'in', 'not_in',
            'is_empty', 'is_not_empty',
            'is_checked', 'is_not_checked',
        ];

        if (!in_array($this->operator, $validOperators, true)) {
            throw new \InvalidArgumentException(
                "Invalid operator '{$this->operator}'. Must be one of: " . implode(', ', $validOperators)
            );
        }
    }

    public function jsonSerialize(): array
    {
        $data = [
            'field' => $this->field,
            'operator' => $this->operator,
        ];

        if ($this->value !== null) {
            $data['value'] = $this->value;
        }

        if ($this->fieldValue !== null) {
            $data['field_value'] = $this->fieldValue;
        }

        return $data;
    }
}
