<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories;

use App\Domain\Modules\Entities\Block as BlockEntity;
use App\Domain\Modules\Repositories\BlockRepositoryInterface;
use App\Domain\Modules\ValueObjects\BlockType;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DbBlockRepository implements BlockRepositoryInterface
{
    private const TABLE = 'blocks';
    private const TABLE_FIELDS = 'fields';
    private const TABLE_FIELD_OPTIONS = 'field_options';

    public function findById(int $id): ?BlockEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntityWithFields($row);
    }

    public function findByModuleId(int $moduleId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->orderBy('display_order')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntityWithFields($row))->all();
    }

    public function save(BlockEntity $block): BlockEntity
    {
        $data = [
            'module_id' => $block->moduleId(),
            'name' => $block->name(),
            'type' => $block->type()->value,
            'display_order' => $block->displayOrder(),
            'settings' => json_encode($block->settings()),
        ];

        if ($block->getId()) {
            DB::table(self::TABLE)
                ->where('id', $block->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $block->getId();
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
        // Get all field IDs for this block
        $fieldIds = DB::table(self::TABLE_FIELDS)
            ->where('block_id', $id)
            ->pluck('id')
            ->all();

        // Delete field options
        if (!empty($fieldIds)) {
            DB::table(self::TABLE_FIELD_OPTIONS)->whereIn('field_id', $fieldIds)->delete();
        }

        // Delete fields
        DB::table(self::TABLE_FIELDS)->where('block_id', $id)->delete();

        // Delete block
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    private function toDomainEntityWithFields(stdClass $row): BlockEntity
    {
        $entity = $this->toDomainEntity($row);

        // Load fields with options
        $fieldRepo = new DbFieldRepository();
        $fields = $fieldRepo->findByBlockId((int) $row->id);

        foreach ($fields as $field) {
            $entity->addField($field);
        }

        return $entity;
    }

    public function toDomainEntity(stdClass $row): BlockEntity
    {
        $settings = $row->settings
            ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings)
            : [];

        return new BlockEntity(
            id: (int) $row->id,
            moduleId: (int) $row->module_id,
            name: $row->name,
            type: BlockType::from($row->type),
            displayOrder: (int) $row->display_order,
            settings: $settings,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }
}
