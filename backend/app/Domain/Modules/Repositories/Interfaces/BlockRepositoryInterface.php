<?php

declare(strict_types=1);

namespace App\Domain\Modules\Repositories\Interfaces;

use App\Domain\Modules\DTOs\BlockDefinitionDTO;
use App\Domain\Modules\DTOs\CreateBlockDTO;
use App\Domain\Modules\DTOs\UpdateBlockDTO;
use App\Models\Block;
use Illuminate\Support\Collection;

/**
 * Repository interface for Block operations.
 */
interface BlockRepositoryInterface
{
    /**
     * Create a new block.
     *
     * @param int $moduleId
     * @param CreateBlockDTO $dto
     * @return Block
     */
    public function create(int $moduleId, CreateBlockDTO $dto): Block;

    /**
     * Update an existing block.
     *
     * @param UpdateBlockDTO $dto
     * @return Block
     */
    public function update(UpdateBlockDTO $dto): Block;

    /**
     * Find block by ID.
     *
     * @param int $id
     * @return Block|null
     */
    public function findById(int $id): ?Block;

    /**
     * Find block by ID with full definition.
     *
     * @param int $id
     * @return BlockDefinitionDTO|null
     */
    public function findByIdWithDefinition(int $id): ?BlockDefinitionDTO;

    /**
     * Get all blocks for a module.
     *
     * @param int $moduleId
     * @return Collection<Block>
     */
    public function getByModule(int $moduleId): Collection;

    /**
     * Get blocks with definitions for a module.
     *
     * @param int $moduleId
     * @return Collection<BlockDefinitionDTO>
     */
    public function getByModuleWithDefinitions(int $moduleId): Collection;

    /**
     * Delete a block by ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Check if block exists by ID.
     *
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool;

    /**
     * Reorder blocks within a module.
     *
     * @param int $moduleId
     * @param array<int, int> $orderMap [block_id => display_order]
     * @return bool
     */
    public function reorder(int $moduleId, array $orderMap): bool;

    /**
     * Get block count for a module.
     *
     * @param int $moduleId
     * @return int
     */
    public function countByModule(int $moduleId): int;
}
