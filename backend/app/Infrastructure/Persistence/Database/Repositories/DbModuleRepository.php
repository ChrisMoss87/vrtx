<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories;

use App\Domain\Modules\Entities\Module as ModuleEntity;
use App\Domain\Modules\Events\ModuleCreated;
use App\Domain\Modules\Events\ModuleDeleted;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleSettings;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DbModuleRepository implements ModuleRepositoryInterface
{
    private const TABLE = 'modules';
    private const TABLE_BLOCKS = 'blocks';
    private const TABLE_FIELDS = 'fields';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AuthContextInterface $authContext,
    ) {}

    public function findById(int $id): ?ModuleEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntityWithRelations($row);
    }

    public function findByApiName(string $apiName): ?ModuleEntity
    {
        $row = DB::table(self::TABLE)
            ->where('api_name', $apiName)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntityWithRelations($row);
    }

    public function findAll(): array
    {
        $rows = DB::table(self::TABLE)
            ->orderBy('display_order')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function findActive(): array
    {
        $rows = DB::table(self::TABLE)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function save(ModuleEntity $module): ModuleEntity
    {
        $isNew = $module->getId() === null;

        $data = [
            'name' => $module->getName(),
            'singular_name' => $module->getSingularName(),
            'api_name' => $module->getApiName(),
            'icon' => $module->getIcon(),
            'description' => $module->getDescription(),
            'is_active' => $module->isActive(),
            'settings' => json_encode($module->getSettings()->jsonSerialize()),
            'display_order' => $module->getDisplayOrder(),
        ];

        if ($isNew) {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        } else {
            DB::table(self::TABLE)
                ->where('id', $module->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $module->getId();
        }

        $savedModule = $this->findById($id);

        // Dispatch domain event for new modules
        if ($isNew && $savedModule !== null) {
            $this->eventDispatcher->dispatch(new ModuleCreated(
                moduleId: $savedModule->getId(),
                name: $savedModule->getName(),
                slug: $savedModule->getApiName(),
                createdBy: $this->authContext->userId(),
            ));
        }

        return $savedModule;
    }

    public function delete(int $id): bool
    {
        // Get module data before deletion for event
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        $deleted = DB::table(self::TABLE)->where('id', $id)->delete() > 0;

        // Dispatch domain event after successful deletion
        if ($deleted && $row !== null) {
            $this->eventDispatcher->dispatch(new ModuleDeleted(
                moduleId: (int) $row->id,
                name: $row->name,
                slug: $row->api_name,
            ));
        }

        return $deleted;
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $query = DB::table(self::TABLE)->where('name', $name);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function existsByApiName(string $apiName, ?int $excludeId = null): bool
    {
        $query = DB::table(self::TABLE)->where('api_name', $apiName);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function toDomainEntityWithRelations(stdClass $row): ModuleEntity
    {
        $blockRepo = new DbBlockRepository();
        $fieldRepo = new DbFieldRepository();

        // Load blocks with fields
        $blocks = $blockRepo->findByModuleId((int) $row->id);

        // Load standalone fields (not in blocks)
        $fields = $fieldRepo->findByModuleId((int) $row->id);

        return $this->toDomainEntity($row, $blocks, $fields);
    }

    private function toDomainEntity(stdClass $row, array $blocks = [], array $fields = []): ModuleEntity
    {
        $settings = $row->settings
            ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings)
            : [];

        return ModuleEntity::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            singularName: $row->singular_name,
            apiName: $row->api_name,
            icon: $row->icon,
            description: $row->description,
            isActive: (bool) $row->is_active,
            settings: ModuleSettings::fromArray($settings),
            displayOrder: (int) $row->display_order,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
            deletedAt: $row->deleted_at ? new DateTimeImmutable($row->deleted_at) : null,
            blocks: $blocks,
            fields: $fields,
        );
    }
}
