<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\CollaborativeDocument;

use App\Domain\CollaborativeDocument\Entities\CollaborativeDocument;
use App\Domain\CollaborativeDocument\Repositories\CollaborativeDocumentRepositoryInterface;
use App\Domain\CollaborativeDocument\ValueObjects\DocumentContent;
use App\Domain\CollaborativeDocument\ValueObjects\DocumentType;
use App\Domain\CollaborativeDocument\ValueObjects\ShareSettings;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbCollaborativeDocumentRepository implements CollaborativeDocumentRepositoryInterface
{
    private const TABLE = 'collaborative_documents';
    private const TABLE_COLLABORATORS = 'document_collaborators';
    private const TABLE_USERS = 'users';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?CollaborativeDocument
    {
        $row = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByIdWithTrashed(int $id): ?CollaborativeDocument
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(CollaborativeDocument $document): CollaborativeDocument
    {
        $data = $this->toRowData($document);

        if ($document->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $document->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $document->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        return $this->findByIdWithTrashed($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->update(['deleted_at' => now()]) > 0;
    }

    public function forceDelete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    public function listDocuments(
        array $filters = [],
        int $perPage = 20,
        int $page = 1,
        string $sortBy = 'updated_at',
        string $sortDirection = 'desc',
    ): PaginatedResult {
        $query = DB::table(self::TABLE . ' as d')
            ->select('d.*');

        // By default, exclude deleted
        if (empty($filters['include_trashed'])) {
            $query->whereNull('d.deleted_at');
        }

        // Filter by owner
        if (!empty($filters['owner_id'])) {
            $query->where('d.owner_id', $filters['owner_id']);
        }

        // Filter by folder (null means root)
        if (array_key_exists('folder_id', $filters)) {
            if ($filters['folder_id'] === null) {
                $query->whereNull('d.parent_folder_id');
            } else {
                $query->where('d.parent_folder_id', $filters['folder_id']);
            }
        }

        // Filter by type
        if (!empty($filters['type'])) {
            $type = $filters['type'] instanceof DocumentType ? $filters['type']->value : $filters['type'];
            $query->where('d.type', $type);
        }

        // Filter by template
        if (isset($filters['is_template'])) {
            $query->where('d.is_template', $filters['is_template']);
        }

        // Filter documents shared with a user
        if (!empty($filters['shared_with_user'])) {
            $userId = $filters['shared_with_user'];
            $query->where(function ($q) use ($userId) {
                $q->where('d.owner_id', $userId)
                    ->orWhereExists(function ($sq) use ($userId) {
                        $sq->select(DB::raw(1))
                            ->from(self::TABLE_COLLABORATORS . ' as c')
                            ->whereColumn('c.document_id', 'd.id')
                            ->where('c.user_id', $userId);
                    });
            });
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('d.title', 'ilike', "%{$search}%")
                    ->orWhere('d.text_content', 'ilike', "%{$search}%");
            });
        }

        // Count total before pagination
        $total = $query->count();

        // Sort
        $allowedSorts = ['updated_at', 'created_at', 'title', 'last_edited_at', 'type'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'updated_at';
        }
        $query->orderBy('d.' . $sortBy, $sortDirection === 'asc' ? 'asc' : 'desc');

        // Paginate
        $offset = ($page - 1) * $perPage;
        $items = $query->limit($perPage)->offset($offset)->get();

        // Transform to arrays with relations
        $itemsArray = [];
        foreach ($items as $item) {
            $itemArray = (array) $item;

            // Load owner
            if ($item->owner_id) {
                $owner = DB::table(self::TABLE_USERS)
                    ->select('id', 'name', 'email')
                    ->where('id', $item->owner_id)
                    ->first();
                $itemArray['owner'] = $owner ? (array) $owner : null;
            }

            // Load last editor
            if ($item->last_edited_by) {
                $editor = DB::table(self::TABLE_USERS)
                    ->select('id', 'name', 'email')
                    ->where('id', $item->last_edited_by)
                    ->first();
                $itemArray['last_editor'] = $editor ? (array) $editor : null;
            }

            // Count collaborators
            $itemArray['collaborator_count'] = DB::table(self::TABLE_COLLABORATORS)
                ->where('document_id', $item->id)
                ->count();

            $itemsArray[] = $itemArray;
        }

        return PaginatedResult::create(
            items: $itemsArray,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function findByFolder(?int $folderId, int $ownerId): array
    {
        $query = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('owner_id', $ownerId);

        if ($folderId === null) {
            $query->whereNull('parent_folder_id');
        } else {
            $query->where('parent_folder_id', $folderId);
        }

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $query->orderBy('title')->get()->all()
        );
    }

    public function findByOwner(int $ownerId, ?DocumentType $type = null): array
    {
        $query = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('owner_id', $ownerId);

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $query->orderBy('updated_at', 'desc')->get()->all()
        );
    }

    public function findSharedWithUser(int $userId): array
    {
        $rows = DB::table(self::TABLE . ' as d')
            ->join(self::TABLE_COLLABORATORS . ' as c', 'c.document_id', '=', 'd.id')
            ->whereNull('d.deleted_at')
            ->where('c.user_id', $userId)
            ->where('d.owner_id', '!=', $userId)
            ->select('d.*')
            ->orderBy('d.updated_at', 'desc')
            ->get();

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $rows->all()
        );
    }

    public function findRecentForUser(int $userId, int $limit = 10): array
    {
        $rows = DB::table(self::TABLE . ' as d')
            ->leftJoin(self::TABLE_COLLABORATORS . ' as c', function ($join) use ($userId) {
                $join->on('c.document_id', '=', 'd.id')
                    ->where('c.user_id', '=', $userId);
            })
            ->whereNull('d.deleted_at')
            ->where(function ($q) use ($userId) {
                $q->where('d.owner_id', $userId)
                    ->orWhereNotNull('c.id');
            })
            ->select('d.*')
            ->orderByRaw('COALESCE(d.last_edited_at, d.updated_at) DESC')
            ->limit($limit)
            ->get();

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $rows->all()
        );
    }

    public function findTemplates(?int $ownerId = null, ?DocumentType $type = null): array
    {
        $query = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('is_template', true);

        if ($ownerId !== null) {
            $query->where('owner_id', $ownerId);
        }

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $query->orderBy('title')->get()->all()
        );
    }

    public function findByShareToken(string $token): ?CollaborativeDocument
    {
        $row = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('is_publicly_shared', true)
            ->whereRaw("share_settings->>'token' = ?", [$token])
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function search(string $query, int $userId, int $limit = 50): array
    {
        $rows = DB::table(self::TABLE . ' as d')
            ->leftJoin(self::TABLE_COLLABORATORS . ' as c', function ($join) use ($userId) {
                $join->on('c.document_id', '=', 'd.id')
                    ->where('c.user_id', '=', $userId);
            })
            ->whereNull('d.deleted_at')
            ->where(function ($q) use ($userId) {
                $q->where('d.owner_id', $userId)
                    ->orWhereNotNull('c.id');
            })
            ->where(function ($q) use ($query) {
                $q->where('d.title', 'ilike', "%{$query}%")
                    ->orWhere('d.text_content', 'ilike', "%{$query}%");
            })
            ->select('d.*')
            ->orderByRaw("CASE WHEN d.title ILIKE ? THEN 0 ELSE 1 END", ["%{$query}%"])
            ->orderBy('d.updated_at', 'desc')
            ->limit($limit)
            ->get();

        return array_map(fn($row) => (array) $row, $rows->all());
    }

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->first();

        return $row ? (array) $row : null;
    }

    public function findByIdWithRelations(int $id, array $relations = []): ?array
    {
        $row = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->first();

        if (!$row) {
            return null;
        }

        $result = (array) $row;

        foreach ($relations as $relation) {
            if ($relation === 'owner' && $row->owner_id) {
                $result['owner'] = DB::table(self::TABLE_USERS)
                    ->select('id', 'name', 'email')
                    ->where('id', $row->owner_id)
                    ->first();
            } elseif ($relation === 'collaborators') {
                $result['collaborators'] = DB::table(self::TABLE_COLLABORATORS . ' as c')
                    ->join(self::TABLE_USERS . ' as u', 'u.id', '=', 'c.user_id')
                    ->where('c.document_id', $id)
                    ->select('c.*', 'u.name as user_name', 'u.email as user_email')
                    ->get()
                    ->map(fn($r) => (array) $r)
                    ->all();
            } elseif ($relation === 'last_editor' && $row->last_edited_by) {
                $result['last_editor'] = DB::table(self::TABLE_USERS)
                    ->select('id', 'name', 'email')
                    ->where('id', $row->last_edited_by)
                    ->first();
            }
        }

        return $result;
    }

    public function getStatistics(int $userId): array
    {
        $ownedCount = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('owner_id', $userId)
            ->count();

        $sharedCount = DB::table(self::TABLE . ' as d')
            ->join(self::TABLE_COLLABORATORS . ' as c', 'c.document_id', '=', 'd.id')
            ->whereNull('d.deleted_at')
            ->where('c.user_id', $userId)
            ->where('d.owner_id', '!=', $userId)
            ->count();

        $byType = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('owner_id', $userId)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->all();

        $recentlyEdited = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('owner_id', $userId)
            ->where('last_edited_at', '>=', now()->subDays(7))
            ->count();

        return [
            'total_owned' => $ownedCount,
            'total_shared_with_me' => $sharedCount,
            'by_type' => $byType,
            'recently_edited' => $recentlyEdited,
        ];
    }

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): CollaborativeDocument
    {
        $shareSettings = null;
        if ($row->share_settings) {
            $settings = json_decode($row->share_settings, true);
            if ($settings) {
                $shareSettings = ShareSettings::fromArray($settings);
            }
        }

        $content = DocumentContent::fromYjsState(
            yjsState: $row->yjs_state ? base64_encode($row->yjs_state) : '',
            htmlSnapshot: $row->html_snapshot,
            textContent: $row->text_content,
        );

        return CollaborativeDocument::reconstitute(
            id: (int) $row->id,
            title: $row->title,
            type: DocumentType::from($row->type),
            content: $content,
            ownerId: (int) $row->owner_id,
            parentFolderId: $row->parent_folder_id ? (int) $row->parent_folder_id : null,
            isTemplate: (bool) $row->is_template,
            isPubliclyShared: (bool) $row->is_publicly_shared,
            shareSettings: $shareSettings,
            currentVersion: (int) $row->current_version,
            lastEditedAt: $row->last_edited_at ? new DateTimeImmutable($row->last_edited_at) : null,
            lastEditedBy: $row->last_edited_by ? (int) $row->last_edited_by : null,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
            deletedAt: $row->deleted_at ? new DateTimeImmutable($row->deleted_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(CollaborativeDocument $entity): array
    {
        return [
            'title' => $entity->getTitle(),
            'type' => $entity->getType()->value,
            'yjs_state' => $entity->getContent()->getYjsStateBinary() ?: null,
            'html_snapshot' => $entity->getContent()->getHtmlSnapshot(),
            'text_content' => $entity->getContent()->getTextContent(),
            'owner_id' => $entity->getOwnerId(),
            'parent_folder_id' => $entity->getParentFolderId(),
            'is_template' => $entity->isTemplate(),
            'is_publicly_shared' => $entity->isPubliclyShared(),
            'share_settings' => $entity->getShareSettings() ? json_encode($entity->getShareSettings()->toArray()) : null,
            'current_version' => $entity->getCurrentVersion(),
            'character_count' => $entity->getContent()->getCharacterCount(),
            'word_count' => $entity->getContent()->getWordCount(),
            'last_edited_at' => $entity->getLastEditedAt()?->format('Y-m-d H:i:s'),
            'last_edited_by' => $entity->getLastEditedBy(),
            'deleted_at' => $entity->getDeletedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
