<?php

declare(strict_types=1);

namespace App\Domain\Modules\DTOs;

use App\Models\Block;
use App\Models\Field;
use App\Models\Module;
use JsonSerializable;

/**
 * DTO representing a complete module definition with all its blocks, fields, and options.
 *
 * This DTO is used for reading/displaying a full module structure, typically for the frontend.
 */
readonly class ModuleDefinitionDTO implements JsonSerializable
{
    /**
     * @param int $id Module ID
     * @param string $name Display name
     * @param string $singularName Singular form
     * @param string $apiName API identifier
     * @param string|null $icon Icon name
     * @param string|null $description Module description
     * @param bool $isActive Whether module is active
     * @param array<string, mixed> $settings Module settings
     * @param int $displayOrder Display order
     * @param array<BlockDefinitionDTO> $blocks Layout blocks with fields
     * @param array<FieldDefinitionDTO> $fields All fields (flat list)
     * @param \DateTimeInterface $createdAt Creation timestamp
     * @param \DateTimeInterface $updatedAt Last update timestamp
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $singularName,
        public string $apiName,
        public ?string $icon,
        public ?string $description,
        public bool $isActive,
        public array $settings,
        public int $displayOrder,
        public array $blocks,
        public array $fields,
        public \DateTimeInterface $createdAt,
        public \DateTimeInterface $updatedAt,
    ) {}

    /**
     * Create from Eloquent model with eager-loaded relationships.
     *
     * @param Module $module
     * @return self
     */
    public static function fromModel(Module $module): self
    {
        // Load relationships if not already loaded
        $module->loadMissing(['blocks.fields.options', 'fields.options']);

        // Build block definitions
        $blocks = [];
        foreach ($module->blocks as $block) {
            $blocks[] = BlockDefinitionDTO::fromModel($block);
        }

        // Build field definitions (flat list of all fields)
        $fields = [];
        foreach ($module->fields as $field) {
            $fields[] = FieldDefinitionDTO::fromModel($field);
        }

        return new self(
            id: $module->id,
            name: $module->name,
            singularName: $module->singular_name,
            apiName: $module->api_name,
            icon: $module->icon,
            description: $module->description,
            isActive: $module->is_active,
            settings: $module->settings,
            displayOrder: $module->display_order,
            blocks: $blocks,
            fields: $fields,
            createdAt: $module->created_at,
            updatedAt: $module->updated_at,
        );
    }

    /**
     * Get fields organized by block.
     *
     * @return array<string, array<FieldDefinitionDTO>>
     */
    public function getFieldsByBlock(): array
    {
        $grouped = [];

        foreach ($this->fields as $field) {
            $blockKey = $field->blockId ?? 'unassigned';
            if (!isset($grouped[$blockKey])) {
                $grouped[$blockKey] = [];
            }
            $grouped[$blockKey][] = $field;
        }

        return $grouped;
    }

    /**
     * Get fields without a block assignment.
     *
     * @return array<FieldDefinitionDTO>
     */
    public function getUnassignedFields(): array
    {
        return array_filter(
            $this->fields,
            fn (FieldDefinitionDTO $field): bool => $field->blockId === null
        );
    }

    /**
     * Get total field count.
     *
     * @return int
     */
    public function getFieldCount(): int
    {
        return count($this->fields);
    }

    /**
     * Get block count.
     *
     * @return int
     */
    public function getBlockCount(): int
    {
        return count($this->blocks);
    }

    /**
     * Find field by API name.
     *
     * @param string $apiName
     * @return FieldDefinitionDTO|null
     */
    public function findFieldByApiName(string $apiName): ?FieldDefinitionDTO
    {
        foreach ($this->fields as $field) {
            if ($field->apiName === $apiName) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Find block by ID.
     *
     * @param int $blockId
     * @return BlockDefinitionDTO|null
     */
    public function findBlockById(int $blockId): ?BlockDefinitionDTO
    {
        foreach ($this->blocks as $block) {
            if ($block->id === $blockId) {
                return $block;
            }
        }

        return null;
    }

    /**
     * Get all required fields.
     *
     * @return array<FieldDefinitionDTO>
     */
    public function getRequiredFields(): array
    {
        return array_filter(
            $this->fields,
            fn (FieldDefinitionDTO $field): bool => $field->isRequired
        );
    }

    /**
     * Get all unique fields.
     *
     * @return array<FieldDefinitionDTO>
     */
    public function getUniqueFields(): array
    {
        return array_filter(
            $this->fields,
            fn (FieldDefinitionDTO $field): bool => $field->isUnique
        );
    }

    /**
     * Get all searchable fields.
     *
     * @return array<FieldDefinitionDTO>
     */
    public function getSearchableFields(): array
    {
        return array_filter(
            $this->fields,
            fn (FieldDefinitionDTO $field): bool => $field->isSearchable
        );
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
            'name' => $this->name,
            'singular_name' => $this->singularName,
            'api_name' => $this->apiName,
            'icon' => $this->icon,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'settings' => $this->settings,
            'display_order' => $this->displayOrder,
            'blocks' => array_map(fn ($block) => $block->jsonSerialize(), $this->blocks),
            'fields' => array_map(fn ($field) => $field->jsonSerialize(), $this->fields),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'meta' => [
                'field_count' => $this->getFieldCount(),
                'block_count' => $this->getBlockCount(),
                'required_field_count' => count($this->getRequiredFields()),
                'unique_field_count' => count($this->getUniqueFields()),
            ],
        ];
    }
}
