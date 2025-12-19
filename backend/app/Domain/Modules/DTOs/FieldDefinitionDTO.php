<?php

declare(strict_types=1);

namespace App\Domain\Modules\DTOs;

use App\Models\Field;
use App\Models\FieldOption;
use JsonSerializable;

/**
 * DTO representing a complete field definition with all its properties and options.
 */
readonly class FieldDefinitionDTO implements JsonSerializable
{
    /**
     * @param int $id Field ID
     * @param int $moduleId Module ID
     * @param int|null $blockId Block ID
     * @param string $label Display label
     * @param string $apiName API identifier
     * @param string $type Field type
     * @param string|null $description Description
     * @param string|null $helpText Help text
     * @param string|null $placeholder Placeholder text
     * @param bool $isRequired Whether field is required
     * @param bool $isUnique Whether field value must be unique
     * @param bool $isSearchable Whether field is searchable
     * @param bool $isFilterable Whether field can be filtered
     * @param bool $isSortable Whether field can be sorted
     * @param array<string> $validationRules Validation rules
     * @param array<string, mixed> $settings Field settings
     * @param array<string, mixed>|null $conditionalVisibility Conditional visibility
     * @param array<string, mixed>|null $fieldDependency Field dependency
     * @param array<string, mixed>|null $formulaDefinition Formula definition
     * @param array<string, mixed>|null $lookupSettings Lookup settings
     * @param string|null $defaultValue Default value
     * @param int $displayOrder Display order
     * @param int $width Field width percentage
     * @param array<FieldOptionDefinitionDTO> $options Field options
     * @param \DateTimeInterface $createdAt Creation timestamp
     * @param \DateTimeInterface $updatedAt Last update timestamp
     */
    public function __construct(
        public int $id,
        public int $moduleId,
        public ?int $blockId,
        public string $label,
        public string $apiName,
        public string $type,
        public ?string $description,
        public ?string $helpText,
        public ?string $placeholder,
        public bool $isRequired,
        public bool $isUnique,
        public bool $isSearchable,
        public bool $isFilterable,
        public bool $isSortable,
        public array $validationRules,
        public array $settings,
        public ?array $conditionalVisibility,
        public ?array $fieldDependency,
        public ?array $formulaDefinition,
        public ?array $lookupSettings,
        public ?string $defaultValue,
        public int $displayOrder,
        public int $width,
        public array $options,
        public \DateTimeInterface $createdAt,
        public \DateTimeInterface $updatedAt,
    ) {}

    /**
     * Create from Eloquent model.
     *
     * @param Field $field
     * @return self
     */
    public static function fromModel(Field $field): self
    {
        // Load options if not already loaded
        $field->loadMissing('options');

        // Build option definitions
        $options = [];
        foreach ($field->options as $option) {
            $options[] = FieldOptionDefinitionDTO::fromModel($option);
        }

        return new self(
            id: $field->id,
            moduleId: $field->module_id,
            blockId: $field->block_id,
            label: $field->label,
            apiName: $field->api_name,
            type: $field->type,
            description: $field->description,
            helpText: $field->help_text,
            placeholder: $field->placeholder,
            isRequired: $field->is_required,
            isUnique: $field->is_unique,
            isSearchable: $field->is_searchable,
            isFilterable: $field->is_filterable,
            isSortable: $field->is_sortable,
            validationRules: $field->validation_rules,
            settings: $field->settings,
            conditionalVisibility: $field->conditional_visibility,
            fieldDependency: $field->field_dependency,
            formulaDefinition: $field->formula_definition,
            lookupSettings: $field->lookup_settings,
            defaultValue: $field->default_value,
            displayOrder: $field->display_order,
            width: $field->width,
            options: $options,
            createdAt: $field->created_at,
            updatedAt: $field->updated_at,
        );
    }

    /**
     * Check if field has conditional visibility.
     *
     * @return bool
     */
    public function hasConditionalVisibility(): bool
    {
        return $this->conditionalVisibility !== null && !empty($this->conditionalVisibility);
    }

    /**
     * Check if field is a formula field.
     *
     * @return bool
     */
    public function isFormulaField(): bool
    {
        return $this->type === 'formula';
    }

    /**
     * Check if field is a lookup field.
     *
     * @return bool
     */
    public function isLookupField(): bool
    {
        return $this->type === 'lookup';
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
     * Get option count.
     *
     * @return int
     */
    public function getOptionCount(): int
    {
        return count($this->options);
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
            'block_id' => $this->blockId,
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
            'options' => array_map(fn ($option) => $option->jsonSerialize(), $this->options),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
