<?php

declare(strict_types=1);

namespace App\Domain\Wizard\Repositories;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Wizard\Entities\Wizard;

/**
 * Repository interface for Wizard aggregate root.
 */
interface WizardRepositoryInterface
{
    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Find a wizard by ID.
     */
    public function findById(int $id): ?Wizard;

    /**
     * Find a wizard by ID as array (for backward compatibility).
     */
    public function findByIdAsArray(int $id): ?array;

    /**
     * Find a wizard by ID with steps and relations.
     */
    public function findByIdWithRelations(int $id): ?array;

    /**
     * Find all wizards with optional filtering.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array>
     */
    public function findAll(array $filters = []): array;

    /**
     * Find wizards with filtering and pagination.
     *
     * @param array<string, mixed> $filters
     */
    public function findWithFilters(array $filters = [], int $perPage = 15): PaginatedResult;

    /**
     * Find wizards for a specific module.
     *
     * @return array<int, array>
     */
    public function findForModule(int $moduleId, bool $activeOnly = true): array;

    /**
     * Find wizards by type.
     *
     * @return array<int, array>
     */
    public function findByType(string $type, bool $activeOnly = true): array;

    /**
     * Find default wizard for a module and type.
     */
    public function findDefaultForModuleAndType(?int $moduleId, string $type): ?array;

    /**
     * Check if wizard exists.
     */
    public function exists(int $id): bool;

    // =========================================================================
    // COMMAND METHODS
    // =========================================================================

    /**
     * Save a wizard entity.
     */
    public function save(Wizard $entity): Wizard;

    /**
     * Create a new wizard with steps.
     *
     * @param array<string, mixed> $data
     * @param array<array<string, mixed>> $steps
     */
    public function create(array $data, array $steps = []): array;

    /**
     * Update a wizard.
     *
     * @param array<string, mixed> $data
     * @param array<array<string, mixed>>|null $steps
     */
    public function update(int $id, array $data, ?array $steps = null): array;

    /**
     * Delete a wizard.
     */
    public function delete(int $id): bool;

    /**
     * Duplicate a wizard.
     */
    public function duplicate(int $id, ?string $newName = null, ?int $createdBy = null): array;

    /**
     * Reorder wizards.
     *
     * @param array<array{id: int, display_order: int}> $order
     */
    public function reorder(array $order): void;

    /**
     * Toggle wizard active status.
     */
    public function toggleActive(int $id): array;

    /**
     * Activate a wizard.
     */
    public function activate(int $id): array;

    /**
     * Deactivate a wizard.
     */
    public function deactivate(int $id): array;

    /**
     * Set wizard as default for its module/type.
     */
    public function setAsDefault(int $id): array;

    /**
     * Unset default flag for other wizards of same module/type.
     */
    public function unsetDefaultsExcept(?int $moduleId, string $type, ?int $exceptId = null): void;

    // =========================================================================
    // WIZARD STEP METHODS
    // =========================================================================

    /**
     * Find steps for a wizard.
     *
     * @return array<int, array>
     */
    public function findSteps(int $wizardId): array;

    /**
     * Find a single step by ID.
     */
    public function findStepById(int $stepId): ?array;

    /**
     * Create a step for a wizard.
     *
     * @param array<string, mixed> $data
     */
    public function createStep(int $wizardId, array $data): array;

    /**
     * Update a step.
     *
     * @param array<string, mixed> $data
     */
    public function updateStep(int $stepId, array $data): array;

    /**
     * Delete a step.
     */
    public function deleteStep(int $stepId): bool;

    /**
     * Reorder steps for a wizard.
     *
     * @param array<array{id: int, display_order: int}> $order
     */
    public function reorderSteps(int $wizardId, array $order): void;

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Get maximum display order for wizards.
     */
    public function getMaxDisplayOrder(): int;

    /**
     * Get maximum display order for steps in a wizard.
     */
    public function getMaxStepDisplayOrder(int $wizardId): int;
}
