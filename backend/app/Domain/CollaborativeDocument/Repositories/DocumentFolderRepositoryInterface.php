<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Repositories;

use App\Domain\CollaborativeDocument\Entities\DocumentFolder;

interface DocumentFolderRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?DocumentFolder;

    public function save(DocumentFolder $folder): DocumentFolder;

    public function delete(int $id): bool;

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Find all folders owned by a user.
     */
    public function findByOwner(int $ownerId): array;

    /**
     * Find folders by parent.
     */
    public function findByParent(?int $parentId, int $ownerId): array;

    /**
     * Get folder tree structure for a user.
     */
    public function getFolderTree(int $ownerId): array;

    /**
     * Get folder path (breadcrumb).
     */
    public function getFolderPath(int $folderId): array;

    /**
     * Check if a folder is a descendant of another.
     */
    public function isDescendantOf(int $folderId, int $ancestorId): bool;

    /**
     * Find folder by name within a parent.
     */
    public function findByNameInParent(string $name, ?int $parentId, int $ownerId): ?DocumentFolder;

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array;
}
