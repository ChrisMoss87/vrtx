<?php

declare(strict_types=1);

namespace App\Domain\Modules\DTOs;

use InvalidArgumentException;
use JsonSerializable;

/**
 * DTO for updating an existing block.
 *
 * All fields except ID are optional - only provided fields will be updated.
 */
readonly class UpdateBlockDTO implements JsonSerializable
{
    /**
     * @param int $id Block ID
     * @param string|null $name Block display name
     * @param string|null $type Block type (section, tab, accordion, card)
     * @param int|null $displayOrder Display order for sorting
     * @param array<string, mixed>|null $settings Block-specific settings
     */
    public function __construct(
        public int $id,
        public ?string $name = null,
        public ?string $type = null,
        public ?int $displayOrder = null,
        public ?array $settings = null,
    ) {
        $this->validate();
    }

    /**
     * Create from array data.
     *
     * @param int $id Block ID
     * @param array<string, mixed> $data Update data
     * @return self
     */
    public static function fromArray(int $id, array $data): self
    {
        return new self(
            id: $id,
            name: $data['name'] ?? null,
            type: $data['type'] ?? null,
            displayOrder: isset($data['display_order']) ? (int) $data['display_order']
                : (isset($data['displayOrder']) ? (int) $data['displayOrder'] : null),
            settings: $data['settings'] ?? null,
        );
    }

    /**
     * Validate DTO data.
     *
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if ($this->id <= 0) {
            throw new InvalidArgumentException('Block ID must be positive');
        }

        if ($this->name !== null && empty(trim($this->name))) {
            throw new InvalidArgumentException('Block name cannot be empty');
        }

        if ($this->name !== null && strlen($this->name) > 255) {
            throw new InvalidArgumentException('Block name cannot exceed 255 characters');
        }

        if ($this->type !== null) {
            $validTypes = ['section', 'tab', 'accordion', 'card'];
            if (!in_array($this->type, $validTypes, true)) {
                throw new InvalidArgumentException(
                    'Block type must be one of: ' . implode(', ', $validTypes)
                );
            }
        }

        if ($this->displayOrder !== null && $this->displayOrder < 0) {
            throw new InvalidArgumentException('Display order cannot be negative');
        }
    }

    /**
     * Convert to array for database update.
     * Only includes non-null values.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->type !== null) {
            $data['type'] = $this->type;
        }

        if ($this->displayOrder !== null) {
            $data['display_order'] = $this->displayOrder;
        }

        if ($this->settings !== null) {
            $data['settings'] = $this->settings;
        }

        return $data;
    }

    /**
     * Check if any updates are provided.
     *
     * @return bool
     */
    public function hasUpdates(): bool
    {
        return !empty($this->toArray());
    }

    /**
     * Get list of fields being updated.
     *
     * @return array<string>
     */
    public function getUpdatedFields(): array
    {
        return array_keys($this->toArray());
    }

    /**
     * JSON serialize the DTO.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(
            ['id' => $this->id],
            $this->toArray()
        );
    }
}
