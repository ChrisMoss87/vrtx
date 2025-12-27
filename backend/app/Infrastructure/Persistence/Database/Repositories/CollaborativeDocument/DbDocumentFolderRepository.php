<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\CollaborativeDocument;

use App\Domain\CollaborativeDocument\Entities\DocumentFolder;
use App\Domain\CollaborativeDocument\Repositories\DocumentFolderRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbDocumentFolderRepository implements DocumentFolderRepositoryInterface
{
    private const TABLE = 'collaborative_document_folders';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?DocumentFolder
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(DocumentFolder $folder): DocumentFolder
    {
        $data = $this->toRowData($folder);

        if ($folder->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $folder->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $folder->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    public function findByOwner(int $ownerId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('owner_id', $ownerId)
            ->orderBy('name')
            ->get();

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $rows->all()
        );
    }

    public function findByParent(?int $parentId, int $ownerId): array
    {
        $query = DB::table(self::TABLE)
            ->where('owner_id', $ownerId);

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        $rows = $query->orderBy('name')->get();

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $rows->all()
        );
    }

    public function getFolderTree(int $ownerId): array
    {
        $folders = DB::table(self::TABLE)
            ->where('owner_id', $ownerId)
            ->orderBy('name')
            ->get()
            ->map(fn($row) => (array) $row)
            ->all();

        return $this->buildTree($folders, null);
    }

    private function buildTree(array $folders, ?int $parentId): array
    {
        $tree = [];

        foreach ($folders as $folder) {
            if ($folder['parent_id'] === $parentId) {
                $children = $this->buildTree($folders, $folder['id']);
                $folder['children'] = $children;
                $tree[] = $folder;
            }
        }

        return $tree;
    }

    public function getFolderPath(int $folderId): array
    {
        $path = [];
        $currentId = $folderId;

        while ($currentId !== null) {
            $folder = DB::table(self::TABLE)->where('id', $currentId)->first();

            if (!$folder) {
                break;
            }

            array_unshift($path, [
                'id' => $folder->id,
                'name' => $folder->name,
            ]);

            $currentId = $folder->parent_id;
        }

        return $path;
    }

    public function isDescendantOf(int $folderId, int $ancestorId): bool
    {
        $currentId = $folderId;

        while ($currentId !== null) {
            $folder = DB::table(self::TABLE)
                ->where('id', $currentId)
                ->first();

            if (!$folder) {
                return false;
            }

            if ($folder->parent_id === $ancestorId) {
                return true;
            }

            $currentId = $folder->parent_id;
        }

        return false;
    }

    public function findByNameInParent(string $name, ?int $parentId, int $ownerId): ?DocumentFolder
    {
        $query = DB::table(self::TABLE)
            ->where('owner_id', $ownerId)
            ->where('name', $name);

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        $row = $query->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): DocumentFolder
    {
        return DocumentFolder::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            parentId: $row->parent_id ? (int) $row->parent_id : null,
            ownerId: (int) $row->owner_id,
            color: $row->color,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(DocumentFolder $entity): array
    {
        return [
            'name' => $entity->getName(),
            'parent_id' => $entity->getParentId(),
            'owner_id' => $entity->getOwnerId(),
            'color' => $entity->getColor(),
        ];
    }
}
