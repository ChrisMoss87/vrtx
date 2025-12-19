<?php

declare(strict_types=1);

namespace App\Domain\Modules\Repositories\Interfaces;

use App\Domain\Modules\DTOs\CreateModuleDTO;
use App\Domain\Modules\DTOs\ModuleDefinitionDTO;
use App\Domain\Modules\DTOs\UpdateModuleDTO;
use App\Models\Module;
use Illuminate\Support\Collection;

/**
 * Repository interface for Module operations.
 */
interface ModuleRepositoryInterface
{
    /**
     * Create a new module with blocks and fields.
     *
     * @param CreateModuleDTO $dto
     * @return Module
     */
    public function create(CreateModuleDTO $dto): Module;

    /**
     * Update an existing module.
     *
     * @param UpdateModuleDTO $dto
     * @return Module
     */
    public function update(UpdateModuleDTO $dto): Module;

    /**
     * Find module by ID.
     *
     * @param int $id
     * @return Module|null
     */
    public function findById(int $id): ?Module;

    /**
     * Find module by ID with all relationships loaded.
     *
     * @param int $id
     * @return ModuleDefinitionDTO|null
     */
    public function findByIdWithDefinition(int $id): ?ModuleDefinitionDTO;

    /**
     * Find module by API name.
     *
     * @param string $apiName
     * @return Module|null
     */
    public function findByApiName(string $apiName): ?Module;

    /**
     * Get all modules.
     *
     * @param bool $activeOnly
     * @return Collection<Module>
     */
    public function all(bool $activeOnly = false): Collection;

    /**
     * Get all modules with full definitions.
     *
     * @param bool $activeOnly
     * @return Collection<ModuleDefinitionDTO>
     */
    public function allWithDefinitions(bool $activeOnly = false): Collection;

    /**
     * Delete a module by ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Restore a soft-deleted module.
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Permanently delete a module.
     *
     * @param int $id
     * @return bool
     */
    public function forceDelete(int $id): bool;

    /**
     * Check if module exists by ID.
     *
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool;

    /**
     * Check if module exists by API name.
     *
     * @param string $apiName
     * @param int|null $excludeId
     * @return bool
     */
    public function existsByApiName(string $apiName, ?int $excludeId = null): bool;

    /**
     * Reorder modules.
     *
     * @param array<int, int> $orderMap [module_id => display_order]
     * @return bool
     */
    public function reorder(array $orderMap): bool;

    /**
     * Get modules ordered by display order.
     *
     * @param bool $activeOnly
     * @return Collection<Module>
     */
    public function getOrdered(bool $activeOnly = true): Collection;

    /**
     * Search modules by name.
     *
     * @param string $searchTerm
     * @param bool $activeOnly
     * @return Collection<Module>
     */
    public function search(string $searchTerm, bool $activeOnly = true): Collection;

    /**
     * Get module count.
     *
     * @param bool $activeOnly
     * @return int
     */
    public function count(bool $activeOnly = false): int;
}
