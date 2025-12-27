<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\CollaborativeDocument;

use App\Domain\CollaborativeDocument\Entities\DocumentComment;
use App\Domain\CollaborativeDocument\Repositories\DocumentCommentRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbDocumentCommentRepository implements DocumentCommentRepositoryInterface
{
    private const TABLE = 'document_comments';
    private const TABLE_USERS = 'users';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?DocumentComment
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

    public function findByIdWithTrashed(int $id): ?DocumentComment
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(DocumentComment $comment): DocumentComment
    {
        $data = $this->toRowData($comment);

        if ($comment->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $comment->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $comment->getId();
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

    public function findThreadsByDocument(int $documentId, bool $includeResolved = true): array
    {
        $query = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('document_id', $documentId)
            ->whereNull('thread_id'); // Only thread starters

        if (!$includeResolved) {
            $query->where('is_resolved', false);
        }

        $rows = $query->orderBy('created_at', 'desc')->get();

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $rows->all()
        );
    }

    public function listByDocument(
        int $documentId,
        int $perPage = 50,
        int $page = 1,
        bool $includeResolved = true,
    ): PaginatedResult {
        $query = DB::table(self::TABLE . ' as c')
            ->whereNull('c.deleted_at')
            ->where('c.document_id', $documentId)
            ->whereNull('c.thread_id'); // Only thread starters

        if (!$includeResolved) {
            $query->where('c.is_resolved', false);
        }

        $total = $query->count();

        $offset = ($page - 1) * $perPage;
        $items = $query
            ->orderBy('c.created_at', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        $itemsArray = [];
        foreach ($items as $item) {
            $itemArray = (array) $item;

            // Load user
            if ($item->user_id) {
                $user = DB::table(self::TABLE_USERS)
                    ->select('id', 'name', 'email')
                    ->where('id', $item->user_id)
                    ->first();
                $itemArray['user'] = $user ? (array) $user : null;
            }

            // Count replies
            $itemArray['reply_count'] = DB::table(self::TABLE)
                ->whereNull('deleted_at')
                ->where('thread_id', $item->id)
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

    public function findRepliesByThread(int $threadId): array
    {
        $rows = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('thread_id', $threadId)
            ->orderBy('created_at')
            ->get();

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $rows->all()
        );
    }

    public function findThreadWithReplies(int $threadId): array
    {
        $thread = $this->findById($threadId);

        if (!$thread) {
            return [];
        }

        $replies = $this->findRepliesByThread($threadId);

        return [
            'thread' => $thread,
            'replies' => $replies,
        ];
    }

    public function findUnresolvedThreads(int $documentId): array
    {
        return $this->findThreadsByDocument($documentId, false);
    }

    public function findByUser(int $userId, int $limit = 50): array
    {
        $rows = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return array_map(
            fn($row) => $this->toDomainEntity($row),
            $rows->all()
        );
    }

    public function countByDocument(int $documentId, bool $includeResolved = true): int
    {
        $query = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('document_id', $documentId);

        if (!$includeResolved) {
            $query->where(function ($q) {
                $q->where('is_resolved', false)
                    ->orWhereNotNull('thread_id'); // Replies don't have is_resolved
            });
        }

        return $query->count();
    }

    public function countUnresolvedThreads(int $documentId): int
    {
        return DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('document_id', $documentId)
            ->whereNull('thread_id')
            ->where('is_resolved', false)
            ->count();
    }

    public function deleteByDocument(int $documentId): int
    {
        return DB::table(self::TABLE)
            ->where('document_id', $documentId)
            ->update(['deleted_at' => now()]);
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

    public function findThreadsWithUserDetails(int $documentId, bool $includeResolved = true): array
    {
        $query = DB::table(self::TABLE . ' as c')
            ->join(self::TABLE_USERS . ' as u', 'u.id', '=', 'c.user_id')
            ->leftJoin(self::TABLE_USERS . ' as r', 'r.id', '=', 'c.resolved_by')
            ->whereNull('c.deleted_at')
            ->where('c.document_id', $documentId)
            ->whereNull('c.thread_id');

        if (!$includeResolved) {
            $query->where('c.is_resolved', false);
        }

        $rows = $query
            ->select(
                'c.*',
                'u.name as user_name',
                'u.email as user_email',
                'r.name as resolved_by_name'
            )
            ->orderBy('c.created_at', 'desc')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $rowArray = (array) $row;

            // Load replies with user details
            $replies = DB::table(self::TABLE . ' as c')
                ->join(self::TABLE_USERS . ' as u', 'u.id', '=', 'c.user_id')
                ->whereNull('c.deleted_at')
                ->where('c.thread_id', $row->id)
                ->select('c.*', 'u.name as user_name', 'u.email as user_email')
                ->orderBy('c.created_at')
                ->get()
                ->map(fn($r) => (array) $r)
                ->all();

            $rowArray['replies'] = $replies;
            $rowArray['reply_count'] = count($replies);

            $result[] = $rowArray;
        }

        return $result;
    }

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): DocumentComment
    {
        $selectionRange = null;
        if ($row->selection_range) {
            $selectionRange = json_decode($row->selection_range, true);
        }

        return DocumentComment::reconstitute(
            id: (int) $row->id,
            documentId: (int) $row->document_id,
            threadId: $row->thread_id ? (int) $row->thread_id : null,
            userId: (int) $row->user_id,
            content: $row->content,
            selectionRange: $selectionRange,
            isResolved: (bool) $row->is_resolved,
            resolvedBy: $row->resolved_by ? (int) $row->resolved_by : null,
            resolvedAt: $row->resolved_at ? new DateTimeImmutable($row->resolved_at) : null,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
            deletedAt: $row->deleted_at ? new DateTimeImmutable($row->deleted_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(DocumentComment $entity): array
    {
        return [
            'document_id' => $entity->getDocumentId(),
            'thread_id' => $entity->getThreadId(),
            'user_id' => $entity->getUserId(),
            'content' => $entity->getContent(),
            'selection_range' => $entity->getSelectionRange() ? json_encode($entity->getSelectionRange()) : null,
            'is_resolved' => $entity->isResolved(),
            'resolved_by' => $entity->getResolvedBy(),
            'resolved_at' => $entity->getResolvedAt()?->format('Y-m-d H:i:s'),
            'deleted_at' => $entity->getDeletedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
