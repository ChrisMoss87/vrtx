<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Modules\Entities\Module as ModuleEntity;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleSettings;
use App\Models\Module;
use DateTimeImmutable;

final class EloquentModuleRepository implements ModuleRepositoryInterface
{
    public function findById(int $id): ?ModuleEntity
    {
        $model = Module::with(['blocks.fields.options', 'fields.options'])
            ->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByApiName(string $apiName): ?ModuleEntity
    {
        $model = Module::with(['blocks.fields.options', 'fields.options'])
            ->where('api_name', $apiName)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(): array
    {
        return Module::with(['blocks.fields.options', 'fields.options'])
            ->orderBy('display_order')
            ->get()
            ->map(fn (Module $model): ModuleEntity => $this->toDomain($model))
            ->all();
    }

    public function findActive(): array
    {
        return Module::with(['blocks.fields.options', 'fields.options'])
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(fn (Module $model): ModuleEntity => $this->toDomain($model))
            ->all();
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
            'settings' => $module->settings()->jsonSerialize(),
            'display_order' => $module->displayOrder(),
        ];

        if ($module->id() === null) {
            $model = Module::create($data);
        } else {
            $model = Module::findOrFail($module->id());
            $model->update($data);
        }

        return $this->toDomain($model->load(['blocks.fields.options', 'fields.options']));
    }

    public function delete(int $id): bool
    {
        return (bool) Module::destroy($id);
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $query = Module::where('name', $name);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function existsByApiName(string $apiName, ?int $excludeId = null): bool
    {
        $query = Module::where('api_name', $apiName);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function toDomain(Module $model): ModuleEntity
    {
        $entity = new ModuleEntity(
            id: $model->id,
            name: $model->name,
            singularName: $model->singular_name,
            apiName: $model->api_name,
            icon: $model->icon,
            description: $model->description,
            isActive: $model->is_active,
            settings: ModuleSettings::fromArray($model->settings ?? []),
            displayOrder: $model->display_order,
            createdAt: new DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at->toDateTimeString()) : null,
            deletedAt: $model->deleted_at ? new DateTimeImmutable($model->deleted_at->toDateTimeString()) : null,
        );

        // Load blocks and fields if present
        if ($model->relationLoaded('blocks')) {
            $blockRepo = new EloquentBlockRepository();
            foreach ($model->blocks as $blockModel) {
                $entity->addBlock($blockRepo->toDomain($blockModel));
            }
        }

        if ($model->relationLoaded('fields')) {
            $fieldRepo = new EloquentFieldRepository();
            foreach ($model->fields as $fieldModel) {
                $entity->addField($fieldRepo->toDomain($fieldModel));
            }
        }

        return $entity;
    }
}
