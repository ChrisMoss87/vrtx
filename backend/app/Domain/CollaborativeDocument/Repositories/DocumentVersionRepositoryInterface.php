<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Repositories;

use App\Domain\CollaborativeDocument\Entities\DocumentVersion;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface DocumentVersionRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?DocumentVersion;

    public function save(DocumentVersion $version): DocumentVersion;

    public function delete(int $id): bool;

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Find versions for a document.
     */
    public function findByDocument(int $documentId): array;

    /**
     * Find versions for a document with pagination.
     */
    public function listByDocument(
        int $documentId,
        int $perPage = 20,
        int $page = 1,
        bool $includeAutoSaves = false,
    ): PaginatedResult;

    /**
     * Get the latest version for a document.
     */
    public function getLatestVersion(int $documentId): ?DocumentVersion;

    /**
     * Get the latest version number for a document.
     */
    public function getLatestVersionNumber(int $documentId): int;

    /**
     * Find a specific version by document and version number.
     */
    public function findByDocumentAndVersion(int $documentId, int $versionNumber): ?DocumentVersion;

    /**
     * Find named versions only (non-auto-save).
     */
    public function findNamedVersions(int $documentId): array;

    /**
     * Delete auto-save versions older than a certain time.
     */
    public function deleteOldAutoSaves(int $documentId, int $keepCount = 50): int;

    /**
     * Get version count for a document.
     */
    public function countByDocument(int $documentId): int;

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array;
}
