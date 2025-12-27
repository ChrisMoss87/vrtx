<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Repositories;

use App\Domain\CollaborativeDocument\Entities\DocumentCollaborator;
use App\Domain\CollaborativeDocument\ValueObjects\DocumentPermission;

interface DocumentCollaboratorRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?DocumentCollaborator;

    public function save(DocumentCollaborator $collaborator): DocumentCollaborator;

    public function delete(int $id): bool;

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Find collaborators for a document.
     */
    public function findByDocument(int $documentId): array;

    /**
     * Find a specific collaborator by document and user.
     */
    public function findByDocumentAndUser(int $documentId, int $userId): ?DocumentCollaborator;

    /**
     * Find all documents a user is collaborating on.
     */
    public function findByUser(int $userId): array;

    /**
     * Find currently active collaborators for a document.
     */
    public function findActiveByDocument(int $documentId): array;

    /**
     * Check if a user has access to a document.
     */
    public function hasAccess(int $documentId, int $userId): bool;

    /**
     * Get user's permission level for a document.
     */
    public function getPermission(int $documentId, int $userId): ?DocumentPermission;

    /**
     * Mark all collaborators as inactive for a document.
     */
    public function markAllInactive(int $documentId): int;

    /**
     * Delete all collaborators for a document.
     */
    public function deleteByDocument(int $documentId): int;

    /**
     * Count collaborators for a document.
     */
    public function countByDocument(int $documentId): int;

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array;

    /**
     * Find collaborators with user details.
     */
    public function findByDocumentWithUserDetails(int $documentId): array;
}
