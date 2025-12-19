<?php

declare(strict_types=1);

namespace App\Domain\Modules\DTOs;

use InvalidArgumentException;
use JsonSerializable;

/**
 * DTO for creating a new module.
 *
 * Encapsulates all data required to create a module with validation.
 */
readonly class CreateModuleDTO implements JsonSerializable
{
    /**
     * @param string $name Display name (e.g., "Contacts")
     * @param string $singularName Singular form (e.g., "Contact")
     * @param string $apiName API identifier (e.g., "contacts")
     * @param string|null $icon Icon name (e.g., "users", "briefcase")
     * @param string|null $description Module description
     * @param bool $isActive Whether module is active
     * @param array<string, mixed> $settings Module settings
     * @param int $displayOrder Display order for sorting
     * @param array<CreateBlockDTO> $blocks Layout blocks for the module
     * @param array<CreateFieldDTO> $fields Fields for the module
     */
    public function __construct(
        public string $name,
        public string $singularName,
        public string $apiName,
        public ?string $icon = null,
        public ?string $description = null,
        public bool $isActive = true,
        public array $settings = [],
        public int $displayOrder = 0,
        public array $blocks = [],
        public array $fields = [],
    ) {
        $this->validate();
    }

    /**
     * Create from array data (typically from API request).
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        // Parse blocks if provided
        $blocks = [];
        if (isset($data['blocks']) && is_array($data['blocks'])) {
            foreach ($data['blocks'] as $blockData) {
                // Check if already a DTO object
                if ($blockData instanceof CreateBlockDTO) {
                    $blocks[] = $blockData;
                } else {
                    $blocks[] = CreateBlockDTO::fromArray($blockData);
                }
            }
        }

        // Parse fields if provided
        $fields = [];
        if (isset($data['fields']) && is_array($data['fields'])) {
            foreach ($data['fields'] as $fieldData) {
                // Check if already a DTO object
                if ($fieldData instanceof CreateFieldDTO) {
                    $fields[] = $fieldData;
                } else {
                    $fields[] = CreateFieldDTO::fromArray($fieldData);
                }
            }
        }

        return new self(
            name: $data['name'],
            singularName: $data['singular_name'] ?? $data['singularName'] ?? $data['name'],
            apiName: $data['api_name'] ?? $data['apiName'] ?? self::generateApiName($data['name']),
            icon: $data['icon'] ?? null,
            description: $data['description'] ?? null,
            isActive: $data['is_active'] ?? $data['isActive'] ?? true,
            settings: $data['settings'] ?? [],
            displayOrder: (int) ($data['display_order'] ?? $data['displayOrder'] ?? 0),
            blocks: $blocks,
            fields: $fields,
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
            throw new InvalidArgumentException('Module name is required');
        }

        if (strlen($this->name) > 255) {
            throw new InvalidArgumentException('Module name cannot exceed 255 characters');
        }

        if (empty(trim($this->singularName))) {
            throw new InvalidArgumentException('Module singular name is required');
        }

        if (empty(trim($this->apiName))) {
            throw new InvalidArgumentException('Module API name is required');
        }

        // Validate API name format (lowercase, alphanumeric, underscores only)
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $this->apiName)) {
            throw new InvalidArgumentException(
                'Module API name must start with a letter and contain only lowercase letters, numbers, and underscores'
            );
        }

        if (strlen($this->apiName) > 100) {
            throw new InvalidArgumentException('Module API name cannot exceed 100 characters');
        }

        if ($this->displayOrder < 0) {
            throw new InvalidArgumentException('Display order cannot be negative');
        }

        // Validate blocks array contains only CreateBlockDTO instances
        foreach ($this->blocks as $block) {
            if (!$block instanceof CreateBlockDTO) {
                throw new InvalidArgumentException('Blocks array must contain only CreateBlockDTO instances');
            }
        }

        // Validate fields array contains only CreateFieldDTO instances
        foreach ($this->fields as $field) {
            if (!$field instanceof CreateFieldDTO) {
                throw new InvalidArgumentException('Fields array must contain only CreateFieldDTO instances');
            }
        }
    }

    /**
     * Generate API name from display name.
     *
     * @param string $name
     * @return string
     */
    private static function generateApiName(string $name): string
    {
        // Convert to lowercase
        $apiName = strtolower($name);

        // Replace spaces and special characters with underscores
        $apiName = preg_replace('/[^a-z0-9]+/', '_', $apiName);

        // Remove leading/trailing underscores
        $apiName = trim($apiName, '_');

        // Ensure it starts with a letter
        if (!empty($apiName) && !preg_match('/^[a-z]/', $apiName)) {
            $apiName = 'm_' . $apiName;
        }

        return $apiName ?: 'module';
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
            'singular_name' => $this->singularName,
            'api_name' => $this->apiName,
            'icon' => $this->icon,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'settings' => $this->settings,
            'display_order' => $this->displayOrder,
        ];
    }

    /**
     * Get blocks data as arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBlocksData(): array
    {
        return array_map(
            fn (CreateBlockDTO $block): array => $block->toArray(),
            $this->blocks
        );
    }

    /**
     * Get fields data as arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFieldsData(): array
    {
        return array_map(
            fn (CreateFieldDTO $field): array => $field->toArray(),
            $this->fields
        );
    }

    /**
     * Check if module has blocks.
     *
     * @return bool
     */
    public function hasBlocks(): bool
    {
        return count($this->blocks) > 0;
    }

    /**
     * Check if module has fields.
     *
     * @return bool
     */
    public function hasFields(): bool
    {
        return count($this->fields) > 0;
    }

    /**
     * JSON serialize the DTO.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
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
        ];
    }
}
