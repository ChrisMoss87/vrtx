<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use JsonSerializable;

/**
 * Configuration for lookup/relationship fields.
 *
 * Defines how a field relates to another module and how the relationship
 * should be displayed and filtered.
 */
final readonly class LookupConfiguration implements JsonSerializable
{
    /**
     * @param int $relatedModuleId ID of the related module
     * @param string $relatedModuleName API name of the related module
     * @param string $displayField Field to show in dropdown (e.g., 'company_name')
     * @param array<string> $searchFields Fields to search when typing
     * @param bool $allowCreate Allow creating new related records inline
     * @param bool $cascadeDelete Delete this record if related record is deleted
     * @param string $relationshipType Type of relationship
     * @param string|null $dependsOn Parent field this lookup depends on
     * @param DependencyFilter|null $dependencyFilter Filter configuration for dependent lookups
     * @param array<string, mixed> $additionalSettings Extra configuration
     */
    public function __construct(
        public int $relatedModuleId,
        public string $relatedModuleName,
        public string $displayField,
        public array $searchFields,
        public bool $allowCreate = false,
        public bool $cascadeDelete = false,
        public string $relationshipType = 'many_to_one',
        public ?string $dependsOn = null,
        public ?DependencyFilter $dependencyFilter = null,
        public array $additionalSettings = [],
    ) {
        $this->validateRelationshipType();
    }

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            relatedModuleId: $data['related_module_id'] ?? 0,
            relatedModuleName: $data['related_module_name'] ?? '',
            displayField: $data['display_field'] ?? 'name',
            searchFields: $data['search_fields'] ?? [],
            allowCreate: $data['allow_create'] ?? false,
            cascadeDelete: $data['cascade_delete'] ?? false,
            relationshipType: $data['relationship_type'] ?? 'many_to_one',
            dependsOn: $data['depends_on'] ?? null,
            dependencyFilter: isset($data['dependency_filter'])
                ? DependencyFilter::fromArray($data['dependency_filter'])
                : null,
            additionalSettings: $data['additional_settings'] ?? [],
        );
    }

    /**
     * Check if this lookup has a parent dependency.
     */
    public function hasDependency(): bool
    {
        return $this->dependsOn !== null && $this->dependencyFilter !== null;
    }

    /**
     * Get the list of fields for quick create modal.
     *
     * @return array<string>
     */
    public function getQuickCreateFields(): array
    {
        return $this->additionalSettings['quick_create_fields'] ?? [];
    }

    /**
     * Check if recent items should be shown.
     */
    public function shouldShowRecent(): bool
    {
        return $this->additionalSettings['show_recent'] ?? false;
    }

    /**
     * Get the limit for recent items.
     */
    public function getRecentLimit(): int
    {
        return $this->additionalSettings['recent_limit'] ?? 10;
    }

    /**
     * Get static filters that should always apply.
     *
     * @return array<array>
     */
    public function getStaticFilters(): array
    {
        return $this->additionalSettings['filters'] ?? [];
    }

    /**
     * Build query constraints for this lookup.
     *
     * @param array<string, mixed> $formData Current form data (for dependency filtering)
     * @return array Query constraints
     */
    public function buildQueryConstraints(array $formData = []): array
    {
        $constraints = [];

        // Add static filters
        foreach ($this->getStaticFilters() as $filter) {
            $constraints[] = [
                'field' => $filter['field'] ?? null,
                'operator' => $filter['operator'] ?? '=',
                'value' => $filter['value'] ?? null,
            ];
        }

        // Add dependency filter if applicable
        if ($this->hasDependency() && isset($formData[$this->dependsOn])) {
            $parentValue = $formData[$this->dependsOn];
            $constraints[] = $this->dependencyFilter->buildConstraint($parentValue);
        }

        return $constraints;
    }

    /**
     * Validate relationship type.
     *
     * @throws \InvalidArgumentException
     */
    private function validateRelationshipType(): void
    {
        $validTypes = ['one_to_one', 'many_to_one', 'many_to_many'];

        if (!in_array($this->relationshipType, $validTypes, true)) {
            throw new \InvalidArgumentException(
                "Invalid relationship type '{$this->relationshipType}'. Must be one of: " . implode(', ', $validTypes)
            );
        }
    }

    public function jsonSerialize(): array
    {
        $data = [
            'related_module_id' => $this->relatedModuleId,
            'related_module_name' => $this->relatedModuleName,
            'display_field' => $this->displayField,
            'search_fields' => $this->searchFields,
            'allow_create' => $this->allowCreate,
            'cascade_delete' => $this->cascadeDelete,
            'relationship_type' => $this->relationshipType,
            'additional_settings' => $this->additionalSettings,
        ];

        if ($this->dependsOn !== null) {
            $data['depends_on'] = $this->dependsOn;
        }

        if ($this->dependencyFilter !== null) {
            $data['dependency_filter'] = $this->dependencyFilter->jsonSerialize();
        }

        return $data;
    }
}
