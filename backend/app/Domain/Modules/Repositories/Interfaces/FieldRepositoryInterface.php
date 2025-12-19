<?php

declare(strict_types=1);

namespace App\Domain\Modules\Repositories\Interfaces;

use App\Domain\Modules\DTOs\CreateFieldDTO;
use App\Domain\Modules\DTOs\FieldDefinitionDTO;
use App\Domain\Modules\DTOs\UpdateFieldDTO;
use App\Models\Field;
use Illuminate\Support\Collection;

/**
 * Repository interface for Field operations.
 */
interface FieldRepositoryInterface
{
    /**
     * Create a new field with options.
     *
     * @param int $moduleId
     * @param CreateFieldDTO $dto
     * @return Field
     */
    public function create(int $moduleId, CreateFieldDTO $dto): Field;

    /**
     * Update an existing field.
     *
     * @param UpdateFieldDTO $dto
     * @return Field
     */
    public function update(UpdateFieldDTO $dto): Field;

    /**
     * Find field by ID.
     *
     * @param int $id
     * @return Field|null
     */
    public function findById(int $id): ?Field;

    /**
     * Find field by ID with full definition.
     *
     * @param int $id
     * @return FieldDefinitionDTO|null
     */
    public function findByIdWithDefinition(int $id): ?FieldDefinitionDTO;

    /**
     * Find field by API name within a module.
     *
     * @param int $moduleId
     * @param string $apiName
     * @return Field|null
     */
    public function findByApiName(int $moduleId, string $apiName): ?Field;

    /**
     * Get all fields for a module.
     *
     * @param int $moduleId
     * @return Collection<Field>
     */
    public function getByModule(int $moduleId): Collection;

    /**
     * Get all fields for a block.
     *
     * @param int $blockId
     * @return Collection<Field>
     */
    public function getByBlock(int $blockId): Collection;

    /**
     * Get fields with definitions for a module.
     *
     * @param int $moduleId
     * @return Collection<FieldDefinitionDTO>
     */
    public function getByModuleWithDefinitions(int $moduleId): Collection;

    /**
     * Delete a field by ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Check if field exists by ID.
     *
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool;

    /**
     * Check if field exists by API name within a module.
     *
     * @param int $moduleId
     * @param string $apiName
     * @param int|null $excludeId
     * @return bool
     */
    public function existsByApiName(int $moduleId, string $apiName, ?int $excludeId = null): bool;

    /**
     * Reorder fields within a module.
     *
     * @param int $moduleId
     * @param array<int, int> $orderMap [field_id => display_order]
     * @return bool
     */
    public function reorder(int $moduleId, array $orderMap): bool;

    /**
     * Move field to a different block.
     *
     * @param int $fieldId
     * @param int|null $blockId
     * @return bool
     */
    public function moveToBlock(int $fieldId, ?int $blockId): bool;

    /**
     * Get required fields for a module.
     *
     * @param int $moduleId
     * @return Collection<Field>
     */
    public function getRequired(int $moduleId): Collection;

    /**
     * Get searchable fields for a module.
     *
     * @param int $moduleId
     * @return Collection<Field>
     */
    public function getSearchable(int $moduleId): Collection;

    /**
     * Get fields with conditional visibility for a module.
     *
     * @param int $moduleId
     * @return Collection<Field>
     */
    public function getWithConditionalVisibility(int $moduleId): Collection;

    /**
     * Get field dependencies for a specific field.
     *
     * @param int $fieldId
     * @return array<string>
     */
    public function getDependencies(int $fieldId): array;

    /**
     * Get fields that depend on a specific field.
     *
     * @param int $moduleId
     * @param string $fieldApiName
     * @return Collection<Field>
     */
    public function getDependentFields(int $moduleId, string $fieldApiName): Collection;
}
