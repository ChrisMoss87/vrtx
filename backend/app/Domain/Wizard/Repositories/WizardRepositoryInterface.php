<?php

declare(strict_types=1);

namespace App\Domain\Wizard\Repositories;

use Illuminate\Support\Collection;

/**
 * Repository interface for Wizard aggregate root.
 */
interface WizardRepositoryInterface
{
    /**
     * Find a wizard by ID.
     */
    public function findById(int $id): ?array;

    /**
     * Find a wizard by ID with steps.
     */
    public function findByIdWithSteps(int $id): ?array;

    /**
     * List all wizards with optional filtering.
     *
     * @return Collection<int, array>
     */
    public function list(
        ?int $moduleId = null,
        ?string $type = null,
        bool $activeOnly = false
    ): Collection;

    /**
     * Get wizards for a specific module.
     *
     * @return Collection<int, array>
     */
    public function getForModule(int $moduleId, bool $activeOnly = true): Collection;

    /**
     * Create a new wizard with steps.
     *
     * @param array<string, mixed> $data
     * @param array<array<string, mixed>> $steps
     */
    public function create(array $data, array $steps): array;

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
    public function duplicate(int $id): array;

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
     * Unset default flag for other wizards of same module/type.
     */
    public function unsetDefaultsExcept(?int $moduleId, string $type, ?int $exceptId = null): void;

    /**
     * Get maximum display order.
     */
    public function getMaxDisplayOrder(): int;
}
