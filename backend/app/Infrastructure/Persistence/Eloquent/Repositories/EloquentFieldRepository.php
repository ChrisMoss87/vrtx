<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Modules\Entities\Field as FieldEntity;
use App\Domain\Modules\Repositories\FieldRepositoryInterface;
use App\Domain\Modules\ValueObjects\FieldSettings;
use App\Domain\Modules\ValueObjects\FieldType;
use App\Domain\Modules\ValueObjects\ValidationRules;
use App\Models\Field;
use DateTimeImmutable;

final class EloquentFieldRepository implements FieldRepositoryInterface
{
    public function findById(int $id): ?FieldEntity
    {
        $model = Field::with('options')->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByModuleId(int $moduleId): array
    {
        return Field::with('options')
            ->where('module_id', $moduleId)
            ->orderBy('display_order')
            ->get()
            ->map(fn (Field $model): FieldEntity => $this->toDomain($model))
            ->all();
    }

    public function findByBlockId(int $blockId): array
    {
        return Field::with('options')
            ->where('block_id', $blockId)
            ->orderBy('display_order')
            ->get()
            ->map(fn (Field $model): FieldEntity => $this->toDomain($model))
            ->all();
    }

    public function save(FieldEntity $field): FieldEntity
    {
        $data = [
            'module_id' => $field->moduleId(),
            'block_id' => $field->blockId(),
            'label' => $field->label(),
            'api_name' => $field->apiName(),
            'type' => $field->type()->value,
            'description' => $field->description(),
            'help_text' => $field->helpText(),
            'is_required' => $field->isRequired(),
            'is_unique' => $field->isUnique(),
            'is_searchable' => $field->isSearchable(),
            'is_filterable' => $field->isFilterable(),
            'is_sortable' => $field->isSortable(),
            'validation_rules' => $field->validationRules()->jsonSerialize(),
            'settings' => $field->settings()->jsonSerialize(),
            'default_value' => $field->defaultValue(),
            'display_order' => $field->displayOrder(),
            'width' => $field->width(),
        ];

        if ($field->id() === null) {
            $model = Field::create($data);
        } else {
            $model = Field::findOrFail($field->id());
            $model->update($data);
        }

        return $this->toDomain($model->load('options'));
    }

    public function delete(int $id): bool
    {
        return (bool) Field::destroy($id);
    }

    public function existsByApiName(int $moduleId, string $apiName, ?int $excludeId = null): bool
    {
        $query = Field::where('module_id', $moduleId)
            ->where('api_name', $apiName);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function toDomain(Field $model): FieldEntity
    {
        $entity = new FieldEntity(
            id: $model->id,
            moduleId: $model->module_id,
            blockId: $model->block_id,
            label: $model->label,
            apiName: $model->api_name,
            type: FieldType::from($model->type),
            description: $model->description,
            helpText: $model->help_text,
            isRequired: $model->is_required,
            isUnique: $model->is_unique,
            isSearchable: $model->is_searchable,
            isFilterable: $model->is_filterable,
            isSortable: $model->is_sortable,
            validationRules: ValidationRules::fromArray($model->validation_rules ?? []),
            settings: FieldSettings::fromArray($model->settings ?? []),
            defaultValue: $model->default_value,
            displayOrder: $model->display_order,
            width: $model->width,
            createdAt: new DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at->toDateTimeString()) : null,
        );

        if ($model->relationLoaded('options')) {
            $optionRepo = new EloquentFieldOptionRepository();
            foreach ($model->options as $optionModel) {
                $entity->addOption($optionRepo->toDomain($optionModel));
            }
        }

        return $entity;
    }
}
