<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\CollaborativeDocument;

use App\Domain\CollaborativeDocument\Entities\DocumentCollaborator;
use App\Domain\CollaborativeDocument\Repositories\DocumentCollaboratorRepositoryInterface;
use App\Domain\CollaborativeDocument\ValueObjects\CursorPosition;
use App\Domain\CollaborativeDocument\ValueObjects\DocumentPermission;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbDocumentCollaboratorRepository implements DocumentCollaboratorRepositoryInterface
{
    private const TABLE = 'document_collaborators';
    private const TABLE_USERS = 'users';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?DocumentCollaborator
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(DocumentCollaborator $collaborator): DocumentCollaborator
    {
        $data = $this->toRowData($collaborator);

        if ($collaborator->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $collaborator->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $collaborator->getId();
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

    public function findByDocument(int $documentId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->get();

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $rows->all()
        );
    }

    public function findByDocumentAndUser(int $documentId, int $userId): ?DocumentCollaborator
    {
        $row = DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->where('user_id', $userId)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByUser(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->get();

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $rows->all()
        );
    }

    public function findActiveByDocument(int $documentId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->where('is_currently_viewing', true)
            ->get();

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $rows->all()
        );
    }

    public function hasAccess(int $documentId, int $userId): bool
    {
        return DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function getPermission(int $documentId, int $userId): ?DocumentPermission
    {
        $permission = DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->where('user_id', $userId)
            ->value('permission');

        if (!$permission) {
            return null;
        }

        return DocumentPermission::from($permission);
    }

    public function markAllInactive(int $documentId): int
    {
        return DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->where('is_currently_viewing', true)
            ->update([
                'is_currently_viewing' => false,
                'cursor_position' => null,
                'updated_at' => now(),
            ]);
    }

    public function deleteByDocument(int $documentId): int
    {
        return DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->delete();
    }

    public function countByDocument(int $documentId): int
    {
        return DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->count();
    }

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public function findByDocumentWithUserDetails(int $documentId): array
    {
        $rows = DB::table(self::TABLE . ' as c')
            ->join(self::TABLE_USERS . ' as u', 'u.id', '=', 'c.user_id')
            ->where('c.document_id', $documentId)
            ->select(
                'c.*',
                'u.name as user_name',
                'u.email as user_email'
            )
            ->get();

        return $rows->map(fn($row) => (array) $row)->all();
    }

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): DocumentCollaborator
    {
        $cursorPosition = null;
        if ($row->cursor_position) {
            $cursor = json_decode($row->cursor_position, true);
            if ($cursor) {
                $cursorPosition = CursorPosition::fromArray($cursor);
            }
        }

        return DocumentCollaborator::reconstitute(
            id: (int) $row->id,
            documentId: (int) $row->document_id,
            userId: (int) $row->user_id,
            permission: DocumentPermission::from($row->permission),
            cursorPosition: $cursorPosition,
            isCurrentlyViewing: (bool) $row->is_currently_viewing,
            lastActiveAt: $row->last_active_at ? new DateTimeImmutable($row->last_active_at) : null,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(DocumentCollaborator $entity): array
    {
        return [
            'document_id' => $entity->getDocumentId(),
            'user_id' => $entity->getUserId(),
            'permission' => $entity->getPermission()->value,
            'cursor_position' => $entity->getCursorPosition() ? json_encode($entity->getCursorPosition()->toArray()) : null,
            'is_currently_viewing' => $entity->isCurrentlyViewing(),
            'last_active_at' => $entity->getLastActiveAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
