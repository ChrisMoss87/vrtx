<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Modules\Entities\Block as BlockEntity;
use App\Domain\Modules\Repositories\BlockRepositoryInterface;
use App\Domain\Modules\ValueObjects\BlockType;
use App\Models\Block;
use DateTimeImmutable;

final class EloquentBlockRepository implements BlockRepositoryInterface
{
    public function findById(int $id): ?BlockEntity
    {
        $model = Block::with(['fields.options'])->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByModuleId(int $moduleId): array
    {
        $models = Block::where('module_id', $moduleId)
            ->with(['fields.options'])
            ->orderBy('display_order')
            ->get();

        return array_map(fn (Block $model) => $this->toDomain($model), $models->all());
    }

    public function save(BlockEntity $block): BlockEntity
    {
        $data = [
            'module_id' => $block->moduleId(),
            'name' => $block->name(),
            'type' => $block->type()->value,
            'display_order' => $block->displayOrder(),
            'settings' => $block->settings(),
        ];

        if ($block->id()) {
            $model = Block::findOrFail($block->id());
            $model->update($data);
        } else {
            $model = Block::create($data);
        }

        return $this->toDomain($model->fresh(['fields.options']));
    }

    public function delete(int $id): bool
    {
        return Block::destroy($id) > 0;
    }

    public function toDomain(Block $model): BlockEntity
    {
        $entity = new BlockEntity(
            id: $model->id,
            moduleId: $model->module_id,
            name: $model->name,
            type: BlockType::from($model->type),
            displayOrder: $model->display_order,
            settings: $model->settings ?? [],
            createdAt: new DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at->toDateTimeString()) : null,
        );

        if ($model->relationLoaded('fields')) {
            $fieldRepo = new EloquentFieldRepository();
            foreach ($model->fields as $fieldModel) {
                $entity->addField($fieldRepo->toDomain($fieldModel));
            }
        }

        return $entity;
    }
}
