<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\CollaborativeDocument;

use App\Domain\CollaborativeDocument\Entities\DocumentVersion;
use App\Domain\CollaborativeDocument\Repositories\DocumentVersionRepositoryInterface;
use App\Domain\CollaborativeDocument\ValueObjects\DocumentContent;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbDocumentVersionRepository implements DocumentVersionRepositoryInterface
{
    private const TABLE = 'document_versions';
    private const TABLE_USERS = 'users';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?DocumentVersion
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(DocumentVersion $version): DocumentVersion
    {
        $data = $this->toRowData($version);

        if ($version->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $version->getId())
                ->update($data);
            $id = $version->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, ['created_at' => now()])
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
            ->orderBy('version_number', 'desc')
            ->get();

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $rows->all()
        );
    }

    public function listByDocument(int $documentId, int $perPage = 20, int $page = 1, bool $includeAutoSaves = false): PaginatedResult
    {
        $query = DB::table(self::TABLE . ' as v')
            ->where('v.document_id', $documentId);

        if (!$includeAutoSaves) {
            $query->where('v.is_auto_save', false);
        }

        $total = $query->count();

        $offset = ($page - 1) * $perPage;
        $items = $query
            ->orderBy('v.version_number', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        $itemsArray = [];
        foreach ($items as $item) {
            $itemArray = (array) $item;

            // Load creator
            if ($item->created_by) {
                $creator = DB::table(self::TABLE_USERS)
                    ->select('id', 'name', 'email')
                    ->where('id', $item->created_by)
                    ->first();
                $itemArray['creator'] = $creator ? (array) $creator : null;
            }

            // Don't include full yjs_state in list
            unset($itemArray['yjs_state']);

            $itemsArray[] = $itemArray;
        }

        return PaginatedResult::create(
            items: $itemsArray,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getLatestVersion(int $documentId): ?DocumentVersion
    {
        $row = DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->orderBy('version_number', 'desc')
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function getLatestVersionNumber(int $documentId): int
    {
        return (int) DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->max('version_number') ?? 0;
    }

    public function findByDocumentAndVersion(int $documentId, int $versionNumber): ?DocumentVersion
    {
        $row = DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->where('version_number', $versionNumber)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findNamedVersions(int $documentId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->where('is_auto_save', false)
            ->whereNotNull('label')
            ->orderBy('version_number', 'desc')
            ->get();

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $rows->all()
        );
    }

    public function deleteOldAutoSaves(int $documentId, int $keepCount = 50): int
    {
        // Get IDs of auto-saves to keep
        $keepIds = DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->where('is_auto_save', true)
            ->orderBy('version_number', 'desc')
            ->limit($keepCount)
            ->pluck('id');

        // Delete older auto-saves
        return DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->where('is_auto_save', true)
            ->whereNotIn('id', $keepIds)
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

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): DocumentVersion
    {
        $content = DocumentContent::fromYjsState(
            yjsState: $row->yjs_state ? base64_encode($row->yjs_state) : '',
            htmlSnapshot: $row->html_snapshot,
        );

        return DocumentVersion::reconstitute(
            id: (int) $row->id,
            documentId: (int) $row->document_id,
            versionNumber: (int) $row->version_number,
            label: $row->label,
            content: $content,
            createdBy: (int) $row->created_by,
            isAutoSave: (bool) $row->is_auto_save,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(DocumentVersion $entity): array
    {
        return [
            'document_id' => $entity->getDocumentId(),
            'version_number' => $entity->getVersionNumber(),
            'label' => $entity->getLabel(),
            'yjs_state' => $entity->getContent()->getYjsStateBinary() ?: null,
            'html_snapshot' => $entity->getContent()->getHtmlSnapshot(),
            'created_by' => $entity->getCreatedBy(),
            'is_auto_save' => $entity->isAutoSave(),
        ];
    }
}
