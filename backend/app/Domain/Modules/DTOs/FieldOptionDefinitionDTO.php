<?php

declare(strict_types=1);

namespace App\Domain\Modules\DTOs;

use JsonSerializable;

/**
 * DTO representing a field option definition.
 */
readonly class FieldOptionDefinitionDTO implements JsonSerializable
{
    /**
     * @param int $id Option ID
     * @param int $fieldId Field ID
     * @param string $label Display label
     * @param string $value Internal value
     * @param string|null $color Option color
     * @param bool $isActive Whether option is active
     * @param int $displayOrder Display order
     * @param array<string, mixed> $metadata Additional metadata
     * @param \DateTimeInterface $createdAt Creation timestamp
     * @param \DateTimeInterface $updatedAt Last update timestamp
     */
    public function __construct(
        public int $id,
        public int $fieldId,
        public string $label,
        public string $value,
        public ?string $color,
        public bool $isActive,
        public int $displayOrder,
        public array $metadata,
        public \DateTimeInterface $createdAt,
        public \DateTimeInterface $updatedAt,
    ) {}

    /**
     * JSON serialize the DTO.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'field_id' => $this->fieldId,
            'label' => $this->label,
            'value' => $this->value,
            'color' => $this->color,
            'is_active' => $this->isActive,
            'display_order' => $this->displayOrder,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
