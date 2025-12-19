<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Modules\Entities\FieldOption as FieldOptionEntity;
use App\Models\FieldOption;
use DateTimeImmutable;

final class EloquentFieldOptionRepository
{
    public function toDomain(FieldOption $model): FieldOptionEntity
    {
        return new FieldOptionEntity(
            id: $model->id,
            fieldId: $model->field_id,
            label: $model->label,
            value: $model->value,
            color: $model->color,
            isActive: $model->is_active,
            displayOrder: $model->display_order,
            createdAt: new DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at->toDateTimeString()) : null,
        );
    }
}
