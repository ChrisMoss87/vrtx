<?php

declare(strict_types=1);

namespace App\Domain\Modules\Repositories;

use App\Domain\Modules\DTOs\BlockDefinitionDTO;
use App\Domain\Modules\DTOs\CreateBlockDTO;
use App\Domain\Modules\DTOs\UpdateBlockDTO;
use App\Domain\Modules\Repositories\Interfaces\BlockRepositoryInterface;
use App\Models\Block;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of Block repository.
 */
class EloquentBlockRepository implements BlockRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(int $moduleId, CreateBlockDTO $dto): Block
    {
        $blockData = array_merge(
            ['module_id' => $moduleId],
            $dto->toArray()
        );

        return Block::create($blockData);
    }

    /**
     * {@inheritdoc}
     */
    public function update(UpdateBlockDTO $dto): Block
    {
        $block = Block::findOrFail($dto->id);
        
        if ($dto->hasUpdates()) {
            $block->update($dto->toArray());
        }

        return $block->fresh();
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Block
    {
        return Block::find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByIdWithDefinition(int $id): ?BlockDefinitionDTO
    {
        $block = Block::with('fields.options')->find($id);

        return $block ? BlockDefinitionDTO::fromModel($block) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getByModule(int $moduleId): Collection
    {
        return Block::where('module_id', $moduleId)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByModuleWithDefinitions(int $moduleId): Collection
    {
        return Block::with('fields.options')
            ->where('module_id', $moduleId)
            ->orderBy('display_order')
            ->get()
            ->map(fn (Block $block) => BlockDefinitionDTO::fromModel($block));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        $block = Block::findOrFail($id);
        return $block->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function exists(int $id): bool
    {
        return Block::where('id', $id)->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function reorder(int $moduleId, array $orderMap): bool
    {
        return DB::transaction(function () use ($moduleId, $orderMap) {
            foreach ($orderMap as $blockId => $displayOrder) {
                Block::where('id', $blockId)
                    ->where('module_id', $moduleId)
                    ->update(['display_order' => $displayOrder]);
            }

            return true;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function countByModule(int $moduleId): int
    {
        return Block::where('module_id', $moduleId)->count();
    }
}
