<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use JsonSerializable;

final readonly class Condition implements JsonSerializable
{
    public function __construct(
        public string $field,
        public string $operator,
        public mixed $value = null,
        public ?string $fieldValue = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            field: $data['field'],
            operator: $data['operator'],
            value: $data['value'] ?? null,
            fieldValue: $data['field_value'] ?? null,
        );
    }

    public function isFieldComparison(): bool
    {
        return $this->fieldValue !== null;
    }

    public function jsonSerialize(): array
    {
        $result = [
            'field' => $this->field,
            'operator' => $this->operator,
        ];

        if ($this->fieldValue !== null) {
            $result['field_value'] = $this->fieldValue;
        } else {
            $result['value'] = $this->value;
        }

        return $result;
    }
}
