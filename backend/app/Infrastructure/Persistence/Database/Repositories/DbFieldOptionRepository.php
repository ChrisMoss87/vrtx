<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories;

use App\Domain\Modules\Entities\FieldOption as FieldOptionEntity;
use App\Domain\Modules\Repositories\FieldOptionRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DbFieldOptionRepository implements FieldOptionRepositoryInterface
{
    private const TABLE = 'field_options';

    public function findById(int $id): ?FieldOptionEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findByFieldId(int $fieldId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('field_id', $fieldId)
            ->orderBy('display_order')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function save(FieldOptionEntity $option): FieldOptionEntity
    {
        $data = [
            'field_id' => $option->fieldId(),
            'label' => $option->label(),
            'value' => $option->value(),
            'color' => $option->color(),
            'is_active' => $option->isActive(),
            'display_order' => $option->displayOrder(),
        ];

        if ($option->id() !== null) {
            DB::table(self::TABLE)
                ->where('id', $option->id())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $option->id();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function deleteByFieldId(int $fieldId): int
    {
        return DB::table(self::TABLE)->where('field_id', $fieldId)->delete();
    }

    public function toDomainEntity(stdClass $row): FieldOptionEntity
    {
        return new FieldOptionEntity(
            id: (int) $row->id,
            fieldId: (int) $row->field_id,
            label: $row->label,
            value: $row->value,
            color: $row->color,
            isActive: (bool) $row->is_active,
            displayOrder: (int) $row->display_order,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }
}
