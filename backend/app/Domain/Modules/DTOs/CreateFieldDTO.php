<?php

declare(strict_types=1);

namespace App\Domain\Modules\DTOs;

use App\Domain\Modules\ValueObjects\ConditionalVisibility;
use App\Domain\Modules\ValueObjects\FieldDependency;
use App\Domain\Modules\ValueObjects\FormulaDefinition;
use App\Domain\Modules\ValueObjects\LookupConfiguration;
use App\Domain\Modules\ValueObjects\ValidationRule;
use InvalidArgumentException;
use JsonSerializable;

/**
 * DTO for creating a new field.
 */
readonly class CreateFieldDTO implements JsonSerializable
{
    /**
     * @param string $label Field display label
     * @param string $apiName Field API identifier
     * @param string $type Field type (text, email, select, etc.)
     * @param string|null $blockApiName API name of the block this field belongs to
     * @param string|null $description Field description
     * @param string|null $helpText Help text for users
     * @param string|null $placeholder Placeholder text
     * @param bool $isRequired Whether field is required
     * @param bool $isUnique Whether field value must be unique
     * @param bool $isSearchable Whether field is searchable
     * @param bool $isFilterable Whether field can be filtered
     * @param bool $isSortable Whether field can be sorted
     * @param array<string> $validationRules Laravel validation rules
     * @param array<string, mixed> $settings Field-specific settings
     * @param array<string, mixed>|null $conditionalVisibility Conditional visibility rules
     * @param array<string, mixed>|null $fieldDependency Field dependency configuration
     * @param array<string, mixed>|null $formulaDefinition Formula definition
     * @param array<string, mixed>|null $lookupSettings Lookup/relationship settings
     * @param string|null $defaultValue Default value
     * @param int $displayOrder Display order for sorting
     * @param int $width Field width percentage (25, 33, 50, 100)
     * @param array<CreateFieldOptionDTO> $options Field options (for select/radio/multiselect)
     */
    public function __construct(
        public string $label,
        public string $apiName,
        public string $type,
        public ?string $blockApiName = null,
        public ?string $description = null,
        public ?string $helpText = null,
        public ?string $placeholder = null,
        public bool $isRequired = false,
        public bool $isUnique = false,
        public bool $isSearchable = true,
        public bool $isFilterable = true,
        public bool $isSortable = true,
        public array $validationRules = [],
        public array $settings = [],
        public ?array $conditionalVisibility = null,
        public ?array $fieldDependency = null,
        public ?array $formulaDefinition = null,
        public ?array $lookupSettings = null,
        public ?string $defaultValue = null,
        public int $displayOrder = 0,
        public int $width = 100,
        public array $options = [],
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
        // Parse field options if provided
        $options = [];
        if (isset($data['options']) && is_array($data['options'])) {
            foreach ($data['options'] as $optionData) {
                $options[] = CreateFieldOptionDTO::fromArray($optionData);
            }
        }

        return new self(
            label: $data['label'],
            apiName: $data['api_name'] ?? $data['apiName'] ?? self::generateApiName($data['label']),
            type: $data['type'],
            blockApiName: $data['block_api_name'] ?? $data['blockApiName'] ?? null,
            description: $data['description'] ?? null,
            helpText: $data['help_text'] ?? $data['helpText'] ?? null,
            placeholder: $data['placeholder'] ?? null,
            isRequired: $data['is_required'] ?? $data['isRequired'] ?? false,
            isUnique: $data['is_unique'] ?? $data['isUnique'] ?? false,
            isSearchable: $data['is_searchable'] ?? $data['isSearchable'] ?? true,
            isFilterable: $data['is_filterable'] ?? $data['isFilterable'] ?? true,
            isSortable: $data['is_sortable'] ?? $data['isSortable'] ?? true,
            validationRules: $data['validation_rules'] ?? $data['validationRules'] ?? [],
            settings: $data['settings'] ?? [],
            conditionalVisibility: $data['conditional_visibility'] ?? $data['conditionalVisibility'] ?? null,
            fieldDependency: $data['field_dependency'] ?? $data['fieldDependency'] ?? null,
            formulaDefinition: $data['formula_definition'] ?? $data['formulaDefinition'] ?? null,
            lookupSettings: $data['lookup_settings'] ?? $data['lookupSettings'] ?? null,
            defaultValue: $data['default_value'] ?? $data['defaultValue'] ?? null,
            displayOrder: (int) ($data['display_order'] ?? $data['displayOrder'] ?? 0),
            width: (int) ($data['width'] ?? 100),
            options: $options,
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
            throw new InvalidArgumentException('Field label is required');
        }

        if (strlen($this->label) > 255) {
            throw new InvalidArgumentException('Field label cannot exceed 255 characters');
        }

        if (empty(trim($this->apiName))) {
            throw new InvalidArgumentException('Field API name is required');
        }

        // Validate API name format
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $this->apiName)) {
            throw new InvalidArgumentException(
                'Field API name must start with a letter and contain only lowercase letters, numbers, and underscores'
            );
        }

        if (strlen($this->apiName) > 100) {
            throw new InvalidArgumentException('Field API name cannot exceed 100 characters');
        }

        // Validate field type
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

        if ($this->displayOrder < 0) {
            throw new InvalidArgumentException('Display order cannot be negative');
        }

        if ($this->width < 1 || $this->width > 100) {
            throw new InvalidArgumentException('Field width must be between 1 and 100');
        }

        // Validate that select/radio/multiselect fields have options
        if (in_array($this->type, ['select', 'radio', 'multiselect'], true) && empty($this->options)) {
            throw new InvalidArgumentException(
                sprintf('Field type "%s" requires at least one option', $this->type)
            );
        }

        // Validate options array
        foreach ($this->options as $option) {
            if (!$option instanceof CreateFieldOptionDTO) {
                throw new InvalidArgumentException('Options array must contain only CreateFieldOptionDTO instances');
            }
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
     * Generate API name from label.
     *
     * @param string $label
     * @return string
     */
    private static function generateApiName(string $label): string
    {
        $apiName = strtolower($label);
        $apiName = preg_replace('/[^a-z0-9]+/', '_', $apiName);
        $apiName = trim($apiName, '_');

        if (!empty($apiName) && !preg_match('/^[a-z]/', $apiName)) {
            $apiName = 'field_' . $apiName;
        }

        return $apiName ?: 'field';
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
            'api_name' => $this->apiName,
            'type' => $this->type,
            'description' => $this->description,
            'help_text' => $this->helpText,
            'placeholder' => $this->placeholder,
            'is_required' => $this->isRequired,
            'is_unique' => $this->isUnique,
            'is_searchable' => $this->isSearchable,
            'is_filterable' => $this->isFilterable,
            'is_sortable' => $this->isSortable,
            'validation_rules' => $this->validationRules,
            'settings' => $this->settings,
            'conditional_visibility' => $this->conditionalVisibility,
            'field_dependency' => $this->fieldDependency,
            'formula_definition' => $this->formulaDefinition,
            'lookup_settings' => $this->lookupSettings,
            'default_value' => $this->defaultValue,
            'display_order' => $this->displayOrder,
            'width' => $this->width,
        ];
    }

    /**
     * Get options data as arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptionsData(): array
    {
        return array_map(
            fn (CreateFieldOptionDTO $option): array => $option->toArray(),
            $this->options
        );
    }

    /**
     * Check if field has options.
     *
     * @return bool
     */
    public function hasOptions(): bool
    {
        return count($this->options) > 0;
    }

    /**
     * JSON serialize the DTO.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(
            $this->toArray(),
            [
                'block_api_name' => $this->blockApiName,
                'options' => array_map(fn ($option) => $option->jsonSerialize(), $this->options),
            ]
        );
    }
}
