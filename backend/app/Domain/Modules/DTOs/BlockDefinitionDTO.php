<?php

declare(strict_types=1);

namespace App\Domain\Modules\DTOs;

use JsonSerializable;

/**
 * DTO representing a complete block definition with its fields.
 */
readonly class BlockDefinitionDTO implements JsonSerializable
{
    /**
     * @param int $id Block ID
     * @param int $moduleId Module ID
     * @param string $name Display name
     * @param string $type Block type (section, tab, accordion, card)
     * @param int $displayOrder Display order
     * @param array<string, mixed> $settings Block settings
     * @param array<FieldDefinitionDTO> $fields Fields in this block
     * @param \DateTimeInterface $createdAt Creation timestamp
     * @param \DateTimeInterface $updatedAt Last update timestamp
     */
    public function __construct(
        public int $id,
        public int $moduleId,
        public string $name,
        public string $type,
        public int $displayOrder,
        public array $settings,
        public array $fields,
        public \DateTimeInterface $createdAt,
        public \DateTimeInterface $updatedAt,
    ) {}

    /**
     * Get field count.
     *
     * @return int
     */
    public function getFieldCount(): int
    {
        return count($this->fields);
    }

    /**
     * JSON serialize the DTO.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'module_id' => $this->moduleId,
            'name' => $this->name,
            'type' => $this->type,
            'display_order' => $this->displayOrder,
            'settings' => $this->settings,
            'fields' => array_map(fn ($field) => $field->jsonSerialize(), $this->fields),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
