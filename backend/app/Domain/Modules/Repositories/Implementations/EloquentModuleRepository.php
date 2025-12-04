<?php

declare(strict_types=1);

namespace App\Domain\Modules\Repositories\Implementations;

use App\Domain\Modules\Entities\Module as ModuleEntity;
use App\Domain\Modules\Entities\Block as BlockEntity;
use App\Domain\Modules\Entities\Field as FieldEntity;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleSettings;
use App\Domain\Modules\ValueObjects\FieldSettings;
use App\Domain\Modules\ValueObjects\ValidationRules;
use App\Models\Module as ModuleModel;
use DateTimeImmutable;

class EloquentModuleRepository implements ModuleRepositoryInterface
{
    public function findById(int $id): ?ModuleEntity
    {
        $model = ModuleModel::with(['blocks.fields.options', 'fields.options'])->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByApiName(string $apiName): ?ModuleEntity
    {
        $model = ModuleModel::with(['blocks.fields.options', 'fields.options'])
            ->where('api_name', $apiName)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findAll(): array
    {
        $models = ModuleModel::with(['blocks.fields.options', 'fields.options'])
            ->ordered()
            ->get();

        return $models->map(fn ($model) => $this->toEntity($model))->all();
    }

    public function findActive(): array
    {
        $models = ModuleModel::with(['blocks.fields.options', 'fields.options'])
            ->active()
            ->ordered()
            ->get();

        return $models->map(fn ($model) => $this->toEntity($model))->all();
    }

    public function save(ModuleEntity $module): ModuleEntity
    {
        $data = [
            'name' => $module->name(),
            'singular_name' => $module->singularName(),
            'api_name' => $module->apiName(),
            'icon' => $module->icon(),
            'description' => $module->description(),
            'is_active' => $module->isActive(),
            'settings' => $module->settings()->toArray(),
            'display_order' => $module->displayOrder(),
        ];

        if ($module->id()) {
            $model = ModuleModel::findOrFail($module->id());
            $model->update($data);
        } else {
            $model = ModuleModel::create($data);
        }

        // Save blocks and fields
        $this->saveBlocks($model, $module->blocks());
        $this->saveFields($model, $module->fields());

        return $this->toEntity($model->fresh(['blocks.fields.options', 'fields.options']));
    }

    public function delete(int $id): bool
    {
        $model = ModuleModel::find($id);

        if (!$model) {
            return false;
        }

        return $model->delete();
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $query = ModuleModel::where('name', $name);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function existsByApiName(string $apiName, ?int $excludeId = null): bool
    {
        $query = ModuleModel::where('api_name', $apiName);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function toEntity(ModuleModel $model): ModuleEntity
    {
        $module = new ModuleEntity(
            id: $model->id,
            name: $model->name,
            singularName: $model->singular_name,
            apiName: $model->api_name,
            icon: $model->icon,
            description: $model->description,
            isActive: $model->is_active,
            settings: ModuleSettings::fromArray($model->settings ?? []),
            displayOrder: $model->display_order,
            createdAt: new DateTimeImmutable($model->created_at),
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at) : null,
            deletedAt: $model->deleted_at ? new DateTimeImmutable($model->deleted_at) : null,
        );

        // Add blocks
        foreach ($model->blocks as $blockModel) {
            $module->addBlock($this->blockToEntity($blockModel));
        }

        // Add fields
        foreach ($model->fields as $fieldModel) {
            $module->addField($this->fieldToEntity($fieldModel));
        }

        return $module;
    }

    private function blockToEntity($blockModel): BlockEntity
    {
        return new BlockEntity(
            id: $blockModel->id,
            moduleId: $blockModel->module_id,
            name: $blockModel->name,
            type: $blockModel->type,
            displayOrder: $blockModel->display_order,
            settings: $blockModel->settings ?? [],
            createdAt: new DateTimeImmutable($blockModel->created_at),
            updatedAt: $blockModel->updated_at ? new DateTimeImmutable($blockModel->updated_at) : null,
        );
    }

    private function fieldToEntity($fieldModel): FieldEntity
    {
        return new FieldEntity(
            id: $fieldModel->id,
            moduleId: $fieldModel->module_id,
            blockId: $fieldModel->block_id,
            label: $fieldModel->label,
            apiName: $fieldModel->api_name,
            type: $fieldModel->type,
            description: $fieldModel->description,
            helpText: $fieldModel->help_text,
            isRequired: $fieldModel->is_required,
            isUnique: $fieldModel->is_unique,
            isSearchable: $fieldModel->is_searchable,
            isFilterable: $fieldModel->is_filterable,
            isSortable: $fieldModel->is_sortable,
            defaultValue: $fieldModel->default_value,
            displayOrder: $fieldModel->display_order,
            width: $fieldModel->width,
            validationRules: ValidationRules::fromArray($fieldModel->validation_rules ?? []),
            settings: FieldSettings::fromArray($fieldModel->settings ?? []),
            createdAt: new DateTimeImmutable($fieldModel->created_at),
            updatedAt: $fieldModel->updated_at ? new DateTimeImmutable($fieldModel->updated_at) : null,
        );
    }

    private function saveBlocks(ModuleModel $model, array $blocks): void
    {
        // This is a simplified implementation
        // In a real scenario, you'd want to handle updates and deletions more carefully
        foreach ($blocks as $block) {
            // Save logic for blocks
        }
    }

    private function saveFields(ModuleModel $model, array $fields): void
    {
        // This is a simplified implementation
        // In a real scenario, you'd want to handle updates and deletions more carefully
        foreach ($fields as $field) {
            // Save logic for fields
        }
    }
}
