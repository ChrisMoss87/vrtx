<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Modules\Entities\Field as FieldEntity;
use App\Domain\Modules\Repositories\FieldRepositoryInterface;
use App\Domain\Modules\ValueObjects\FieldSettings;
use App\Domain\Modules\ValueObjects\FieldType;
use App\Domain\Modules\ValueObjects\ValidationRules;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class EloquentFieldRepository implements FieldRepositoryInterface
{
    private const TABLE = 'fields';
    private const TABLE_OPTIONS = 'field_options';

    public function findById(int $id): ?FieldEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntityWithOptions($row);
    }

    public function findByModuleId(int $moduleId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->orderBy('display_order')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntityWithOptions($row))->all();
    }

    public function findByBlockId(int $blockId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('block_id', $blockId)
            ->orderBy('display_order')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntityWithOptions($row))->all();
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
            'validation_rules' => json_encode($field->validationRules()->jsonSerialize()),
            'settings' => json_encode($field->settings()->jsonSerialize()),
            'default_value' => $field->defaultValue(),
            'display_order' => $field->displayOrder(),
            'width' => $field->width(),
        ];

        if ($field->id() === null) {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        } else {
            DB::table(self::TABLE)
                ->where('id', $field->id())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $field->id();
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        // Delete related options first
        DB::table(self::TABLE_OPTIONS)->where('field_id', $id)->delete();

        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function existsByApiName(int $moduleId, string $apiName, ?int $excludeId = null): bool
    {
        $query = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('api_name', $apiName);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function toDomainEntityWithOptions(stdClass $row): FieldEntity
    {
        $entity = $this->toDomainEntity($row);

        // Load options
        $options = DB::table(self::TABLE_OPTIONS)
            ->where('field_id', $row->id)
            ->orderBy('display_order')
            ->get();

        $optionRepo = new EloquentFieldOptionRepository();
        foreach ($options as $optionRow) {
            $entity->addOption($optionRepo->toDomainEntity($optionRow));
        }

        return $entity;
    }

    public function toDomainEntity(stdClass $row): FieldEntity
    {
        $validationRules = $row->validation_rules
            ? (is_string($row->validation_rules) ? json_decode($row->validation_rules, true) : $row->validation_rules)
            : [];
        $settings = $row->settings
            ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings)
            : [];

        return new FieldEntity(
            id: (int) $row->id,
            moduleId: (int) $row->module_id,
            blockId: $row->block_id ? (int) $row->block_id : null,
            label: $row->label,
            apiName: $row->api_name,
            type: FieldType::from($row->type),
            description: $row->description,
            helpText: $row->help_text,
            isRequired: (bool) $row->is_required,
            isUnique: (bool) $row->is_unique,
            isSearchable: (bool) $row->is_searchable,
            isFilterable: (bool) $row->is_filterable,
            isSortable: (bool) $row->is_sortable,
            validationRules: ValidationRules::fromArray($validationRules),
            settings: FieldSettings::fromArray($settings),
            defaultValue: $row->default_value,
            displayOrder: (int) $row->display_order,
            width: $row->width,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }
}
