<?php

declare(strict_types=1);

namespace App\Domain\Workflow\ValueObjects;

use JsonSerializable;

/**
 * Value object representing workflow trigger configuration.
 */
final readonly class TriggerConfig implements JsonSerializable
{
    /**
     * @param array<string> $fields Fields to watch for changes
     * @param FieldChangeType $changeType Type of field change to detect
     * @param mixed $fromValue Value the field is changing from
     * @param mixed $toValue Value the field is changing to
     * @param string|null $relatedModule Related module for related record triggers
     * @param string|null $cronExpression Cron expression for scheduled triggers
     * @param string|null $dateField Field to use for date-based scheduling
     * @param int|null $offsetDays Days offset for date-based scheduling
     * @param string|null $offsetDirection 'before' or 'after' for date-based scheduling
     */
    public function __construct(
        private array $fields = [],
        private FieldChangeType $changeType = FieldChangeType::ANY,
        private mixed $fromValue = null,
        private mixed $toValue = null,
        private ?string $relatedModule = null,
        private ?string $cronExpression = null,
        private ?string $dateField = null,
        private ?int $offsetDays = null,
        private ?string $offsetDirection = null,
    ) {}

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fields: $data['fields'] ?? [],
            changeType: isset($data['change_type'])
                ? FieldChangeType::from($data['change_type'])
                : FieldChangeType::ANY,
            fromValue: $data['from_value'] ?? null,
            toValue: $data['to_value'] ?? null,
            relatedModule: $data['related_module'] ?? null,
            cronExpression: $data['cron_expression'] ?? null,
            dateField: $data['date_field'] ?? null,
            offsetDays: isset($data['offset_days']) ? (int) $data['offset_days'] : null,
            offsetDirection: $data['offset_direction'] ?? null,
        );
    }

    /**
     * @return array<string>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function changeType(): FieldChangeType
    {
        return $this->changeType;
    }

    public function fromValue(): mixed
    {
        return $this->fromValue;
    }

    public function toValue(): mixed
    {
        return $this->toValue;
    }

    public function relatedModule(): ?string
    {
        return $this->relatedModule;
    }

    public function cronExpression(): ?string
    {
        return $this->cronExpression;
    }

    public function dateField(): ?string
    {
        return $this->dateField;
    }

    public function offsetDays(): ?int
    {
        return $this->offsetDays;
    }

    public function offsetDirection(): ?string
    {
        return $this->offsetDirection;
    }

    public function hasFieldsToWatch(): bool
    {
        return !empty($this->fields);
    }

    public function toArray(): array
    {
        return [
            'fields' => $this->fields,
            'change_type' => $this->changeType->value,
            'from_value' => $this->fromValue,
            'to_value' => $this->toValue,
            'related_module' => $this->relatedModule,
            'cron_expression' => $this->cronExpression,
            'date_field' => $this->dateField,
            'offset_days' => $this->offsetDays,
            'offset_direction' => $this->offsetDirection,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
