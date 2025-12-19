<?php

declare(strict_types=1);

namespace App\Domain\Modules\DTOs;

use InvalidArgumentException;
use JsonSerializable;

/**
 * DTO for creating a field option (for select/radio/multiselect fields).
 */
readonly class CreateFieldOptionDTO implements JsonSerializable
{
    /**
     * @param string $label Display label
     * @param string $value Internal value
     * @param string|null $color Option color (hex code)
     * @param bool $isActive Whether option is active
     * @param int $displayOrder Display order for sorting
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        public string $label,
        public string $value,
        public ?string $color = null,
        public bool $isActive = true,
        public int $displayOrder = 0,
        public array $metadata = [],
    ) {
        $this->validate();
    }

    /**
     * Create from array data.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            label: $data['label'],
            value: $data['value'] ?? strtolower(str_replace(' ', '_', $data['label'])),
            color: $data['color'] ?? null,
            isActive: $data['is_active'] ?? $data['isActive'] ?? true,
            displayOrder: (int) ($data['display_order'] ?? $data['displayOrder'] ?? 0),
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * Validate DTO data.
     *
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if (empty(trim($this->label))) {
            throw new InvalidArgumentException('Option label is required');
        }

        if (strlen($this->label) > 255) {
            throw new InvalidArgumentException('Option label cannot exceed 255 characters');
        }

        if (empty(trim($this->value))) {
            throw new InvalidArgumentException('Option value is required');
        }

        if (strlen($this->value) > 255) {
            throw new InvalidArgumentException('Option value cannot exceed 255 characters');
        }

        // Validate color format if provided
        if ($this->color !== null && !preg_match('/^#[0-9A-Fa-f]{6}$/', $this->color)) {
            throw new InvalidArgumentException('Color must be a valid hex code (e.g., #FF0000)');
        }

        if ($this->displayOrder < 0) {
            throw new InvalidArgumentException('Display order cannot be negative');
        }
    }

    /**
     * Convert to array for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
            'color' => $this->color,
            'is_active' => $this->isActive,
            'display_order' => $this->displayOrder,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * JSON serialize the DTO.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
