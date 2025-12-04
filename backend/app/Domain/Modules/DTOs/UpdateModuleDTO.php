<?php

declare(strict_types=1);

namespace App\Domain\Modules\DTOs;

use InvalidArgumentException;
use JsonSerializable;

/**
 * DTO for updating an existing module.
 *
 * All fields except ID are optional - only provided fields will be updated.
 */
readonly class UpdateModuleDTO implements JsonSerializable
{
    /**
     * @param int $id Module ID
     * @param string|null $name Display name
     * @param string|null $singularName Singular form
     * @param string|null $apiName API identifier
     * @param string|null $icon Icon name
     * @param string|null $description Module description
     * @param bool|null $isActive Whether module is active
     * @param array<string, mixed>|null $settings Module settings
     * @param int|null $displayOrder Display order for sorting
     */
    public function __construct(
        public int $id,
        public ?string $name = null,
        public ?string $singularName = null,
        public ?string $apiName = null,
        public ?string $icon = null,
        public ?string $description = null,
        public ?bool $isActive = null,
        public ?array $settings = null,
        public ?int $displayOrder = null,
    ) {
        $this->validate();
    }

    /**
     * Create from array data.
     *
     * @param int $id Module ID
     * @param array<string, mixed> $data Update data
     * @return self
     */
    public static function fromArray(int $id, array $data): self
    {
        return new self(
            id: $id,
            name: $data['name'] ?? null,
            singularName: $data['singular_name'] ?? $data['singularName'] ?? null,
            apiName: $data['api_name'] ?? $data['apiName'] ?? null,
            icon: $data['icon'] ?? null,
            description: $data['description'] ?? null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] 
                : (isset($data['isActive']) ? (bool) $data['isActive'] : null),
            settings: $data['settings'] ?? null,
            displayOrder: isset($data['display_order']) ? (int) $data['display_order']
                : (isset($data['displayOrder']) ? (int) $data['displayOrder'] : null),
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
            throw new InvalidArgumentException('Module ID must be positive');
        }

        if ($this->name !== null && empty(trim($this->name))) {
            throw new InvalidArgumentException('Module name cannot be empty');
        }

        if ($this->name !== null && strlen($this->name) > 255) {
            throw new InvalidArgumentException('Module name cannot exceed 255 characters');
        }

        if ($this->singularName !== null && empty(trim($this->singularName))) {
            throw new InvalidArgumentException('Module singular name cannot be empty');
        }

        if ($this->apiName !== null) {
            if (empty(trim($this->apiName))) {
                throw new InvalidArgumentException('Module API name cannot be empty');
            }

            if (!preg_match('/^[a-z][a-z0-9_]*$/', $this->apiName)) {
                throw new InvalidArgumentException(
                    'Module API name must start with a letter and contain only lowercase letters, numbers, and underscores'
                );
            }

            if (strlen($this->apiName) > 100) {
                throw new InvalidArgumentException('Module API name cannot exceed 100 characters');
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

        if ($this->singularName !== null) {
            $data['singular_name'] = $this->singularName;
        }

        if ($this->apiName !== null) {
            $data['api_name'] = $this->apiName;
        }

        if ($this->icon !== null) {
            $data['icon'] = $this->icon;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->isActive !== null) {
            $data['is_active'] = $this->isActive;
        }

        if ($this->settings !== null) {
            $data['settings'] = $this->settings;
        }

        if ($this->displayOrder !== null) {
            $data['display_order'] = $this->displayOrder;
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
