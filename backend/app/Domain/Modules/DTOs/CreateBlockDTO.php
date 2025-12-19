<?php

declare(strict_types=1);

namespace App\Domain\Modules\DTOs;

use InvalidArgumentException;
use JsonSerializable;

/**
 * DTO for creating a new block (form layout section).
 */
readonly class CreateBlockDTO implements JsonSerializable
{
    /**
     * @param string $name Block display name
     * @param string $type Block type (section, tab, accordion, card)
     * @param int $displayOrder Display order for sorting
     * @param array<string, mixed> $settings Block-specific settings
     */
    public function __construct(
        public string $name,
        public string $type = 'section',
        public int $displayOrder = 0,
        public array $settings = [],
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
            name: $data['name'],
            type: $data['type'] ?? 'section',
            displayOrder: (int) ($data['display_order'] ?? $data['displayOrder'] ?? 0),
            settings: $data['settings'] ?? [],
        );
    }

    /**
     * Validate DTO data.
     *
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if (empty(trim($this->name))) {
            throw new InvalidArgumentException('Block name is required');
        }

        if (strlen($this->name) > 255) {
            throw new InvalidArgumentException('Block name cannot exceed 255 characters');
        }

        $validTypes = ['section', 'tab', 'accordion', 'card'];
        if (!in_array($this->type, $validTypes, true)) {
            throw new InvalidArgumentException(
                'Block type must be one of: ' . implode(', ', $validTypes)
            );
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
            'name' => $this->name,
            'type' => $this->type,
            'display_order' => $this->displayOrder,
            'settings' => $this->settings,
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
