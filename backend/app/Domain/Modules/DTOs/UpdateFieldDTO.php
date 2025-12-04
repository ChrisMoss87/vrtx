<?php

declare(strict_types=1);

namespace App\Domain\Modules\DTOs;

use App\Domain\Modules\ValueObjects\ConditionalVisibility;
use App\Domain\Modules\ValueObjects\LookupConfiguration;
use InvalidArgumentException;
use JsonSerializable;

/**
 * DTO for updating an existing field.
 *
 * All fields except ID are optional - only provided fields will be updated.
 */
readonly class UpdateFieldDTO implements JsonSerializable
{
    /**
     * @param int $id Field ID
     * @param string|null $label Field display label
     * @param string|null $apiName Field API identifier
     * @param string|null $type Field type
     * @param string|null $blockApiName API name of the block
     * @param string|null $description Field description
     * @param string|null $helpText Help text
     * @param string|null $placeholder Placeholder text
     * @param bool|null $isRequired Whether field is required
     * @param bool|null $isUnique Whether field value must be unique
     * @param bool|null $isSearchable Whether field is searchable
     * @param bool|null $isFilterable Whether field can be filtered
     * @param bool|null $isSortable Whether field can be sorted
     * @param array<string>|null $validationRules Validation rules
     * @param array<string, mixed>|null $settings Field settings
     * @param array<string, mixed>|null $conditionalVisibility Conditional visibility rules
     * @param array<string, mixed>|null $fieldDependency Field dependency
     * @param array<string, mixed>|null $formulaDefinition Formula definition
     * @param array<string, mixed>|null $lookupSettings Lookup settings
     * @param string|null $defaultValue Default value
     * @param int|null $displayOrder Display order
     * @param int|null $width Field width percentage
     */
    public function __construct(
        public int $id,
        public ?string $label = null,
        public ?string $apiName = null,
        public ?string $type = null,
        public ?string $blockApiName = null,
        public ?string $description = null,
        public ?string $helpText = null,
        public ?string $placeholder = null,
        public ?bool $isRequired = null,
        public ?bool $isUnique = null,
        public ?bool $isSearchable = null,
        public ?bool $isFilterable = null,
        public ?bool $isSortable = null,
        public ?array $validationRules = null,
        public ?array $settings = null,
        public ?array $conditionalVisibility = null,
        public ?array $fieldDependency = null,
        public ?array $formulaDefinition = null,
        public ?array $lookupSettings = null,
        public ?string $defaultValue = null,
        public ?int $displayOrder = null,
        public ?int $width = null,
    ) {
        $this->validate();
    }

    /**
     * Create from array data.
     *
     * @param int $id Field ID
     * @param array<string, mixed> $data Update data
     * @return self
     */
    public static function fromArray(int $id, array $data): self
    {
        return new self(
            id: $id,
            label: $data['label'] ?? null,
            apiName: $data['api_name'] ?? $data['apiName'] ?? null,
            type: $data['type'] ?? null,
            blockApiName: $data['block_api_name'] ?? $data['blockApiName'] ?? null,
            description: $data['description'] ?? null,
            helpText: $data['help_text'] ?? $data['helpText'] ?? null,
            placeholder: $data['placeholder'] ?? null,
            isRequired: isset($data['is_required']) ? (bool) $data['is_required']
                : (isset($data['isRequired']) ? (bool) $data['isRequired'] : null),
            isUnique: isset($data['is_unique']) ? (bool) $data['is_unique']
                : (isset($data['isUnique']) ? (bool) $data['isUnique'] : null),
            isSearchable: isset($data['is_searchable']) ? (bool) $data['is_searchable']
                : (isset($data['isSearchable']) ? (bool) $data['isSearchable'] : null),
            isFilterable: isset($data['is_filterable']) ? (bool) $data['is_filterable']
                : (isset($data['isFilterable']) ? (bool) $data['isFilterable'] : null),
            isSortable: isset($data['is_sortable']) ? (bool) $data['is_sortable']
                : (isset($data['isSortable']) ? (bool) $data['isSortable'] : null),
            validationRules: $data['validation_rules'] ?? $data['validationRules'] ?? null,
            settings: $data['settings'] ?? null,
            conditionalVisibility: $data['conditional_visibility'] ?? $data['conditionalVisibility'] ?? null,
            fieldDependency: $data['field_dependency'] ?? $data['fieldDependency'] ?? null,
            formulaDefinition: $data['formula_definition'] ?? $data['formulaDefinition'] ?? null,
            lookupSettings: $data['lookup_settings'] ?? $data['lookupSettings'] ?? null,
            defaultValue: $data['default_value'] ?? $data['defaultValue'] ?? null,
            displayOrder: isset($data['display_order']) ? (int) $data['display_order']
                : (isset($data['displayOrder']) ? (int) $data['displayOrder'] : null),
            width: isset($data['width']) ? (int) $data['width'] : null,
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
            throw new InvalidArgumentException('Field ID must be positive');
        }

        if ($this->label !== null && empty(trim($this->label))) {
            throw new InvalidArgumentException('Field label cannot be empty');
        }

        if ($this->label !== null && strlen($this->label) > 255) {
            throw new InvalidArgumentException('Field label cannot exceed 255 characters');
        }

        if ($this->apiName !== null) {
            if (empty(trim($this->apiName))) {
                throw new InvalidArgumentException('Field API name cannot be empty');
            }

            if (!preg_match('/^[a-z][a-z0-9_]*$/', $this->apiName)) {
                throw new InvalidArgumentException(
                    'Field API name must start with a letter and contain only lowercase letters, numbers, and underscores'
                );
            }

            if (strlen($this->apiName) > 100) {
                throw new InvalidArgumentException('Field API name cannot exceed 100 characters');
            }
        }

        if ($this->type !== null) {
            $validTypes = [
                'text', 'email', 'phone', 'url', 'textarea', 'rich_text',
                'number', 'currency', 'percent', 'date', 'datetime', 'time',
                'checkbox', 'select', 'multiselect', 'radio', 'lookup',
                'file', 'image', 'formula', 'auto_number'
            ];

            if (!in_array($this->type, $validTypes, true)) {
                throw new InvalidArgumentException(
                    'Field type must be one of: ' . implode(', ', $validTypes)
                );
            }
        }

        if ($this->displayOrder !== null && $this->displayOrder < 0) {
            throw new InvalidArgumentException('Display order cannot be negative');
        }

        if ($this->width !== null && ($this->width < 1 || $this->width > 100)) {
            throw new InvalidArgumentException('Field width must be between 1 and 100');
        }

        // Validate value objects if provided
        if ($this->conditionalVisibility !== null) {
            ConditionalVisibility::fromArray($this->conditionalVisibility);
        }

        if ($this->lookupSettings !== null) {
            LookupConfiguration::fromArray($this->lookupSettings);
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

        if ($this->label !== null) {
            $data['label'] = $this->label;
        }

        if ($this->apiName !== null) {
            $data['api_name'] = $this->apiName;
        }

        if ($this->type !== null) {
            $data['type'] = $this->type;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->helpText !== null) {
            $data['help_text'] = $this->helpText;
        }

        if ($this->placeholder !== null) {
            $data['placeholder'] = $this->placeholder;
        }

        if ($this->isRequired !== null) {
            $data['is_required'] = $this->isRequired;
        }

        if ($this->isUnique !== null) {
            $data['is_unique'] = $this->isUnique;
        }

        if ($this->isSearchable !== null) {
            $data['is_searchable'] = $this->isSearchable;
        }

        if ($this->isFilterable !== null) {
            $data['is_filterable'] = $this->isFilterable;
        }

        if ($this->isSortable !== null) {
            $data['is_sortable'] = $this->isSortable;
        }

        if ($this->validationRules !== null) {
            $data['validation_rules'] = $this->validationRules;
        }

        if ($this->settings !== null) {
            $data['settings'] = $this->settings;
        }

        if ($this->conditionalVisibility !== null) {
            $data['conditional_visibility'] = $this->conditionalVisibility;
        }

        if ($this->fieldDependency !== null) {
            $data['field_dependency'] = $this->fieldDependency;
        }

        if ($this->formulaDefinition !== null) {
            $data['formula_definition'] = $this->formulaDefinition;
        }

        if ($this->lookupSettings !== null) {
            $data['lookup_settings'] = $this->lookupSettings;
        }

        if ($this->defaultValue !== null) {
            $data['default_value'] = $this->defaultValue;
        }

        if ($this->displayOrder !== null) {
            $data['display_order'] = $this->displayOrder;
        }

        if ($this->width !== null) {
            $data['width'] = $this->width;
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
            [
                'id' => $this->id,
                'block_api_name' => $this->blockApiName,
            ],
            $this->toArray()
        );
    }
}
