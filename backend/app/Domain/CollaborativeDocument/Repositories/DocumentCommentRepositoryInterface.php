<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Repositories;

use App\Domain\CollaborativeDocument\Entities\DocumentComment;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface DocumentCommentRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?DocumentComment;

    public function findByIdWithTrashed(int $id): ?DocumentComment;

    public function save(DocumentComment $comment): DocumentComment;

    public function delete(int $id): bool;

    public function forceDelete(int $id): bool;

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Find comments for a document (thread starters only).
     */
    public function findThreadsByDocument(int $documentId, bool $includeResolved = true): array;

    /**
     * Find comments for a document with pagination.
     */
    public function listByDocument(
        int $documentId,
        int $perPage = 50,
        int $page = 1,
        bool $includeResolved = true,
    ): PaginatedResult;

    /**
     * Find replies for a thread.
     */
    public function findRepliesByThread(int $threadId): array;

    /**
     * Find a thread with all its replies.
     */
    public function findThreadWithReplies(int $threadId): array;

    /**
     * Find unresolved threads for a document.
     */
    public function findUnresolvedThreads(int $documentId): array;

    /**
     * Find comments by user.
     */
    public function findByUser(int $userId, int $limit = 50): array;

    /**
     * Count comments for a document.
     */
    public function countByDocument(int $documentId, bool $includeResolved = true): int;

    /**
     * Count unresolved threads for a document.
     */
    public function countUnresolvedThreads(int $documentId): int;

    /**
     * Delete all comments for a document.
     */
    public function deleteByDocument(int $documentId): int;

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array;

    /**
     * Find threads with user details.
     */
    public function findThreadsWithUserDetails(int $documentId, bool $includeResolved = true): array;
}
