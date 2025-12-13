<?php

declare(strict_types=1);

namespace App\Domain\Workflow\ValueObjects;

use JsonSerializable;

/**
 * Value object representing conditions for a workflow step.
 */
final readonly class StepConditions implements JsonSerializable
{
    /**
     * @param array<array{field: string, operator: string, value: mixed}> $conditions
     * @param string $operator Logical operator: 'and' or 'or'
     */
    public function __construct(
        private array $conditions = [],
        private string $operator = 'and'
    ) {}

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        if (empty($data)) {
            return new self();
        }

        // Handle both flat array of conditions and structured format
        if (isset($data['conditions'])) {
            return new self(
                conditions: $data['conditions'],
                operator: $data['operator'] ?? 'and'
            );
        }

        // Assume it's a flat array of conditions
        return new self(conditions: $data);
    }

    /**
     * @return array<array{field: string, operator: string, value: mixed}>
     */
    public function conditions(): array
    {
        return $this->conditions;
    }

    public function operator(): string
    {
        return $this->operator;
    }

    public function isEmpty(): bool
    {
        return empty($this->conditions);
    }

    public function hasConditions(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Get the number of conditions.
     */
    public function count(): int
    {
        return count($this->conditions);
    }

    public function toArray(): array
    {
        if ($this->isEmpty()) {
            return [];
        }

        return [
            'conditions' => $this->conditions,
            'operator' => $this->operator,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
