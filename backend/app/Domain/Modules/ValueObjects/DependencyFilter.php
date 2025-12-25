<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use JsonSerializable;

/**
 * Represents a filter for dependent lookup fields.
 *
 * When a lookup field depends on another field, this defines how to filter
 * the options based on the parent field's value.
 *
 * Example: Contact lookup filtered by Account
 * - field: 'account_id' (field in related module to filter by)
 * - operator: 'equals'
 * - targetField: 'account_id' (field in current form to get value from)
 */
final readonly class DependencyFilter implements JsonSerializable
{
    /**
     * @param string $field Field in the related module to filter by
     * @param string $operator Comparison operator
     * @param string $targetField Field in current form to get filter value from
     * @param mixed $staticValue Optional static value instead of field reference
     */
    public function __construct(
        public string $field,
        public string $operator,
        public string $targetField,
        public mixed $staticValue = null,
    ) {
        $this->validateOperator();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            field: $data['field'] ?? '',
            operator: $data['operator'] ?? 'equals',
            targetField: $data['target_field'] ?? '',
            staticValue: $data['static_value'] ?? null,
        );
    }

    /**
     * Build a query constraint from this filter.
     *
     * @param mixed $parentValue The value from the parent field
     * @return array Query constraint
     */
    public function buildConstraint(mixed $parentValue): array
    {
        $value = $this->staticValue ?? $parentValue;

        return [
            'field' => $this->field,
            'operator' => $this->mapOperator(),
            'value' => $value,
        ];
    }

    /**
     * Map internal operator to query operator.
     */
    private function mapOperator(): string
    {
        return match ($this->operator) {
            'equals' => '=',
            'not_equals' => '!=',
            'greater_than' => '>',
            'less_than' => '<',
            'greater_than_or_equal' => '>=',
            'less_than_or_equal' => '<=',
            'in' => 'in',
            'not_in' => 'not_in',
            'contains' => 'like',
            default => '=',
        };
    }

    /**
     * Build WHERE clause for Database query.
     *
     * @param mixed $parentValue
     * @return array [method, parameters]
     */
    public function buildWhereClause(mixed $parentValue): array
    {
        $value = $this->staticValue ?? $parentValue;

        return match ($this->operator) {
            'equals' => ['where', [$this->field, '=', $value]],
            'not_equals' => ['where', [$this->field, '!=', $value]],
            'greater_than' => ['where', [$this->field, '>', $value]],
            'less_than' => ['where', [$this->field, '<', $value]],
            'greater_than_or_equal' => ['where', [$this->field, '>=', $value]],
            'less_than_or_equal' => ['where', [$this->field, '<=', $value]],
            'in' => ['whereIn', [$this->field, (array) $value]],
            'not_in' => ['whereNotIn', [$this->field, (array) $value]],
            'contains' => ['where', [$this->field, 'like', "%{$value}%"]],
            default => ['where', [$this->field, '=', $value]],
        };
    }

    /**
     * Check if this filter uses a static value.
     */
    public function hasStaticValue(): bool
    {
        return $this->staticValue !== null;
    }

    /**
     * Validate the operator is supported.
     */
    private function validateOperator(): void
    {
        $validOperators = [
            'equals',
            'not_equals',
            'greater_than',
            'less_than',
            'greater_than_or_equal',
            'less_than_or_equal',
            'in',
            'not_in',
            'contains',
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
            'target_field' => $this->targetField,
        ];

        if ($this->staticValue !== null) {
            $data['static_value'] = $this->staticValue;
        }

        return $data;
    }
}
