<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Repositories;

use App\Domain\CollaborativeDocument\Entities\CollaborativeDocument;
use App\Domain\CollaborativeDocument\ValueObjects\DocumentType;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface CollaborativeDocumentRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?CollaborativeDocument;

    public function findByIdWithTrashed(int $id): ?CollaborativeDocument;

    public function save(CollaborativeDocument $document): CollaborativeDocument;

    public function delete(int $id): bool;

    public function forceDelete(int $id): bool;

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * List documents with filtering and pagination.
     *
     * @param array{
     *     owner_id?: int,
     *     folder_id?: int|null,
     *     type?: DocumentType,
     *     is_template?: bool,
     *     search?: string,
     *     include_trashed?: bool,
     *     shared_with_user?: int,
     * } $filters
     */
    public function listDocuments(
        array $filters = [],
        int $perPage = 20,
        int $page = 1,
        string $sortBy = 'updated_at',
        string $sortDirection = 'desc',
    ): PaginatedResult;

    /**
     * Find documents by folder.
     */
    public function findByFolder(?int $folderId, int $ownerId): array;

    /**
     * Find documents owned by a user.
     */
    public function findByOwner(int $ownerId, ?DocumentType $type = null): array;

    /**
     * Find documents shared with a user.
     */
    public function findSharedWithUser(int $userId): array;

    /**
     * Find recent documents for a user (owned or shared).
     */
    public function findRecentForUser(int $userId, int $limit = 10): array;

    /**
     * Find templates accessible to a user.
     */
    public function findTemplates(?int $ownerId = null, ?DocumentType $type = null): array;

    /**
     * Find document by share token.
     */
    public function findByShareToken(string $token): ?CollaborativeDocument;

    /**
     * Search documents by title or content.
     */
    public function search(string $query, int $userId, int $limit = 50): array;

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array;

    public function findByIdWithRelations(int $id, array $relations = []): ?array;

    /**
     * Get document statistics for a user.
     */
    public function getStatistics(int $userId): array;
}
