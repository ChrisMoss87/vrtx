<?php

declare(strict_types=1);

namespace App\Domain\Modules\Repositories;

use App\Domain\Modules\DTOs\CreateFieldDTO;
use App\Domain\Modules\DTOs\FieldDefinitionDTO;
use App\Domain\Modules\DTOs\UpdateFieldDTO;
use App\Domain\Modules\Repositories\Interfaces\FieldRepositoryInterface;
use App\Models\Field;
use App\Models\FieldOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of Field repository.
 */
class EloquentFieldRepository implements FieldRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(int $moduleId, CreateFieldDTO $dto): Field
    {
        return DB::transaction(function () use ($moduleId, $dto) {
            // Prepare field data
            $fieldData = array_merge(
                ['module_id' => $moduleId],
                $dto->toArray()
            );

            // Set block_id if blockApiName is provided
            if ($dto->blockApiName !== null) {
                $block = DB::table('blocks')
                    ->where('module_id', $moduleId)
                    ->where('name', $dto->blockApiName)
                    ->first();

                if ($block) {
                    $fieldData['block_id'] = $block->id;
                }
            }

            // Create field
            $field = Field::create($fieldData);

            // Create options if provided
            if ($dto->hasOptions()) {
                foreach ($dto->options as $optionDTO) {
                    FieldOption::create(array_merge(
                        ['field_id' => $field->id],
                        $optionDTO->toArray()
                    ));
                }
            }

            // Reload with options
            return $field->load('options');
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(UpdateFieldDTO $dto): Field
    {
        $field = Field::findOrFail($dto->id);
        
        if ($dto->hasUpdates()) {
            // Update field
            $field->update($dto->toArray());
        }

        return $field->fresh();
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Field
    {
        return Field::find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByIdWithDefinition(int $id): ?FieldDefinitionDTO
    {
        $field = Field::with('options')->find($id);

        return $field ? FieldDefinitionDTO::fromModel($field) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByApiName(int $moduleId, string $apiName): ?Field
    {
        return Field::where('module_id', $moduleId)
            ->where('api_name', $apiName)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getByModule(int $moduleId): Collection
    {
        return Field::where('module_id', $moduleId)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByBlock(int $blockId): Collection
    {
        return Field::where('block_id', $blockId)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByModuleWithDefinitions(int $moduleId): Collection
    {
        return Field::with('options')
            ->where('module_id', $moduleId)
            ->orderBy('display_order')
            ->get()
            ->map(fn (Field $field) => FieldDefinitionDTO::fromModel($field));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        $field = Field::findOrFail($id);
        return $field->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function exists(int $id): bool
    {
        return Field::where('id', $id)->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function existsByApiName(int $moduleId, string $apiName, ?int $excludeId = null): bool
    {
        $query = Field::where('module_id', $moduleId)
            ->where('api_name', $apiName);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function reorder(int $moduleId, array $orderMap): bool
    {
        return DB::transaction(function () use ($moduleId, $orderMap) {
            foreach ($orderMap as $fieldId => $displayOrder) {
                Field::where('id', $fieldId)
                    ->where('module_id', $moduleId)
                    ->update(['display_order' => $displayOrder]);
            }

            return true;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function moveToBlock(int $fieldId, ?int $blockId): bool
    {
        $field = Field::findOrFail($fieldId);
        return $field->update(['block_id' => $blockId]);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequired(int $moduleId): Collection
    {
        return Field::where('module_id', $moduleId)
            ->where('is_required', true)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchable(int $moduleId): Collection
    {
        return Field::where('module_id', $moduleId)
            ->where('is_searchable', true)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getWithConditionalVisibility(int $moduleId): Collection
    {
        return Field::where('module_id', $moduleId)
            ->whereNotNull('conditional_visibility')
            ->orderBy('display_order')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies(int $fieldId): array
    {
        $field = Field::findOrFail($fieldId);
        return $field->getDependencies();
    }

    /**
     * {@inheritdoc}
     */
    public function getDependentFields(int $moduleId, string $fieldApiName): Collection
    {
        // Get all fields with conditional visibility
        $fieldsWithVisibility = $this->getWithConditionalVisibility($moduleId);

        // Filter fields that depend on the given field
        return $fieldsWithVisibility->filter(function (Field $field) use ($fieldApiName) {
            $dependencies = $field->getDependencies();
            return in_array($fieldApiName, $dependencies, true);
        });
    }
}
