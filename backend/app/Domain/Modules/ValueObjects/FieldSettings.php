<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use JsonSerializable;

final readonly class FieldSettings implements JsonSerializable
{
    public function __construct(
        public ?int $minLength,
        public ?int $maxLength,
        public ?float $minValue,
        public ?float $maxValue,
        public ?string $pattern,
        public ?int $precision, // For decimal/currency fields
        public ?string $currencyCode, // For currency fields
        public ?int $relatedModuleId, // For lookup fields
        public ?string $relatedModuleName, // For lookup fields
        public ?string $displayField, // For lookup fields
        public ?array $searchFields, // For lookup fields
        public ?bool $allowCreate, // For lookup fields
        public ?bool $cascadeDelete, // For lookup fields
        public ?string $relationshipType, // For lookup fields
        public ?string $formula, // For formula fields (deprecated - use formulaDefinition)
        public ?FormulaDefinition $formulaDefinition, // For formula fields
        public ?ConditionalVisibility $conditionalVisibility, // Show/hide based on conditions
        public ?FieldDependency $fieldDependency, // Field dependencies (cascading)
        public ?array $allowedFileTypes, // For file/image fields
        public ?int $maxFileSize, // In KB
        public ?string $placeholder, // Placeholder text
        public ?string $dependsOn, // Simple dependency field name
        public ?array $dependencyFilter, // Dependency filter configuration
        // Progress mapper settings
        public ?ProgressMappingConfig $progressMapping, // For progress_mapper fields
        // Rating field settings
        public ?int $maxRating, // Max rating value (default 5)
        public ?bool $allowHalf, // Allow half ratings
        public ?string $ratingIcon, // Icon type: 'star', 'heart', 'circle'
        // Auto-number settings
        public ?string $prefix, // Prefix for auto-numbers (e.g., 'INV-')
        public ?string $suffix, // Suffix for auto-numbers
        public ?int $startNumber, // Starting number
        public ?int $padLength, // Zero-padding length
        public array $additionalSettings,
    ) {}

    public static function default(): self
    {
        return new self(
            minLength: null,
            maxLength: null,
            minValue: null,
            maxValue: null,
            pattern: null,
            precision: 2,
            currencyCode: 'USD',
            relatedModuleId: null,
            relatedModuleName: null,
            displayField: null,
            searchFields: null,
            allowCreate: false,
            cascadeDelete: false,
            relationshipType: 'many_to_one',
            formula: null,
            formulaDefinition: null,
            conditionalVisibility: null,
            fieldDependency: null,
            allowedFileTypes: null,
            maxFileSize: 5120, // 5MB default
            placeholder: null,
            dependsOn: null,
            dependencyFilter: null,
            progressMapping: null,
            maxRating: 5,
            allowHalf: false,
            ratingIcon: 'star',
            prefix: null,
            suffix: null,
            startNumber: 1,
            padLength: 4,
            additionalSettings: [],
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            minLength: $data['min_length'] ?? null,
            maxLength: $data['max_length'] ?? null,
            minValue: isset($data['min_value']) ? (float) $data['min_value'] : null,
            maxValue: isset($data['max_value']) ? (float) $data['max_value'] : null,
            pattern: $data['pattern'] ?? null,
            precision: $data['precision'] ?? null,
            currencyCode: $data['currency_code'] ?? null,
            relatedModuleId: $data['related_module_id'] ?? null,
            relatedModuleName: $data['related_module_name'] ?? null,
            displayField: $data['display_field'] ?? null,
            searchFields: $data['search_fields'] ?? null,
            allowCreate: $data['allow_create'] ?? null,
            cascadeDelete: $data['cascade_delete'] ?? null,
            relationshipType: $data['relationship_type'] ?? null,
            formula: $data['formula'] ?? null,
            formulaDefinition: isset($data['formula_definition'])
                ? FormulaDefinition::fromArray($data['formula_definition'])
                : (isset($data['formula']) ? FormulaDefinition::fromArray($data) : null),
            conditionalVisibility: isset($data['conditional_visibility'])
                ? ConditionalVisibility::fromArray($data['conditional_visibility'])
                : null,
            fieldDependency: isset($data['field_dependency'])
                ? FieldDependency::fromArray($data['field_dependency'])
                : (isset($data['depends_on']) ? FieldDependency::fromArray($data) : null),
            allowedFileTypes: $data['allowed_file_types'] ?? null,
            maxFileSize: $data['max_file_size'] ?? null,
            placeholder: $data['placeholder'] ?? null,
            dependsOn: $data['depends_on'] ?? null,
            dependencyFilter: $data['dependency_filter'] ?? null,
            progressMapping: isset($data['progress_mapping'])
                ? ProgressMappingConfig::fromArray($data['progress_mapping'])
                : null,
            maxRating: $data['max_rating'] ?? null,
            allowHalf: $data['allow_half'] ?? null,
            ratingIcon: $data['rating_icon'] ?? null,
            prefix: $data['prefix'] ?? null,
            suffix: $data['suffix'] ?? null,
            startNumber: $data['start_number'] ?? null,
            padLength: $data['pad_length'] ?? null,
            additionalSettings: $data['additional_settings'] ?? [],
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'min_length' => $this->minLength,
            'max_length' => $this->maxLength,
            'min_value' => $this->minValue,
            'max_value' => $this->maxValue,
            'pattern' => $this->pattern,
            'precision' => $this->precision,
            'currency_code' => $this->currencyCode,
            'related_module_id' => $this->relatedModuleId,
            'related_module_name' => $this->relatedModuleName,
            'display_field' => $this->displayField,
            'search_fields' => $this->searchFields,
            'allow_create' => $this->allowCreate,
            'cascade_delete' => $this->cascadeDelete,
            'relationship_type' => $this->relationshipType,
            'formula' => $this->formula,
            'formula_definition' => $this->formulaDefinition?->jsonSerialize(),
            'conditional_visibility' => $this->conditionalVisibility?->jsonSerialize(),
            'field_dependency' => $this->fieldDependency?->jsonSerialize(),
            'allowed_file_types' => $this->allowedFileTypes,
            'max_file_size' => $this->maxFileSize,
            'placeholder' => $this->placeholder,
            'depends_on' => $this->dependsOn,
            'dependency_filter' => $this->dependencyFilter,
            'progress_mapping' => $this->progressMapping?->jsonSerialize(),
            'max_rating' => $this->maxRating,
            'allow_half' => $this->allowHalf,
            'rating_icon' => $this->ratingIcon,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'start_number' => $this->startNumber,
            'pad_length' => $this->padLength,
            'additional_settings' => $this->additionalSettings,
        ], static fn ($value): bool => $value !== null && $value !== []);
    }
}
