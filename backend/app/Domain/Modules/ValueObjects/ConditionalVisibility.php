<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use JsonSerializable;

final readonly class ConditionalVisibility implements JsonSerializable
{
    /**
     * @param bool $enabled
     * @param string $operator 'and' or 'or'
     * @param array<Condition> $conditions
     */
    public function __construct(
        public bool $enabled,
        public string $operator,
        public array $conditions,
    ) {}

    public static function disabled(): self
    {
        return new self(
            enabled: false,
            operator: 'and',
            conditions: [],
        );
    }

    public static function fromArray(array $data): self
    {
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

    public function isEnabled(): bool
    {
        return $this->enabled && count($this->conditions) > 0;
    }

    public function jsonSerialize(): array
    {
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
