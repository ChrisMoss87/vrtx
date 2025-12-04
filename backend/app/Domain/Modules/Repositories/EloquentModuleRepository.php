<?php

declare(strict_types=1);

namespace App\Domain\Modules\Repositories;

use App\Domain\Modules\DTOs\CreateModuleDTO;
use App\Domain\Modules\DTOs\ModuleDefinitionDTO;
use App\Domain\Modules\DTOs\UpdateModuleDTO;
use App\Domain\Modules\Repositories\Interfaces\BlockRepositoryInterface;
use App\Domain\Modules\Repositories\Interfaces\FieldRepositoryInterface;
use App\Domain\Modules\Repositories\Interfaces\ModuleRepositoryInterface;
use App\Models\Module;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of Module repository.
 */
class EloquentModuleRepository implements ModuleRepositoryInterface
{
    public function __construct(
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly FieldRepositoryInterface $fieldRepository,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function create(CreateModuleDTO $dto): Module
    {
        return DB::transaction(function () use ($dto) {
            // Create module
            $module = Module::create($dto->toArray());

            // Create blocks if provided
            foreach ($dto->blocks as $blockDTO) {
                $this->blockRepository->create($module->id, $blockDTO);
            }

            // Create fields if provided
            foreach ($dto->fields as $fieldDTO) {
                $this->fieldRepository->create($module->id, $fieldDTO);
            }

            // Reload with relationships
            return $module->load(['blocks', 'fields']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(UpdateModuleDTO $dto): Module
    {
        $module = Module::findOrFail($dto->id);
        
        if ($dto->hasUpdates()) {
            $module->update($dto->toArray());
        }

        return $module->fresh();
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Module
    {
        return Module::find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByIdWithDefinition(int $id): ?ModuleDefinitionDTO
    {
        $module = Module::with(['blocks.fields.options', 'fields.options'])
            ->find($id);

        return $module ? ModuleDefinitionDTO::fromModel($module) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByApiName(string $apiName): ?Module
    {
        return Module::where('api_name', $apiName)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function all(bool $activeOnly = false): Collection
    {
        $query = Module::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('display_order')->get();
    }

    /**
     * {@inheritdoc}
     */
    public function allWithDefinitions(bool $activeOnly = false): Collection
    {
        $query = Module::with(['blocks.fields.options', 'fields.options']);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('display_order')
            ->get()
            ->map(fn (Module $module) => ModuleDefinitionDTO::fromModel($module));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        $module = Module::findOrFail($id);
        return $module->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function restore(int $id): bool
    {
        $module = Module::withTrashed()->findOrFail($id);
        return $module->restore();
    }

    /**
     * {@inheritdoc}
     */
    public function forceDelete(int $id): bool
    {
        $module = Module::withTrashed()->findOrFail($id);
        return $module->forceDelete();
    }

    /**
     * {@inheritdoc}
     */
    public function exists(int $id): bool
    {
        return Module::where('id', $id)->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function existsByApiName(string $apiName, ?int $excludeId = null): bool
    {
        $query = Module::where('api_name', $apiName);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function reorder(array $orderMap): bool
    {
        return DB::transaction(function () use ($orderMap) {
            foreach ($orderMap as $moduleId => $displayOrder) {
                Module::where('id', $moduleId)->update([
                    'display_order' => $displayOrder,
                ]);
            }

            return true;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getOrdered(bool $activeOnly = true): Collection
    {
        $query = Module::query()->orderBy('display_order');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $searchTerm, bool $activeOnly = true): Collection
    {
        $query = Module::query()
            ->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('singular_name', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('api_name', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'ILIKE', "%{$searchTerm}%");
            });

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('display_order')->get();
    }

    /**
     * {@inheritdoc}
     */
    public function count(bool $activeOnly = false): int
    {
        $query = Module::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->count();
    }
}
