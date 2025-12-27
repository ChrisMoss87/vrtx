<?php

declare(strict_types=1);

namespace App\Application\Services\CollaborativeDocument;

use App\Domain\CollaborativeDocument\Entities\CollaborativeDocument;
use App\Domain\CollaborativeDocument\Entities\DocumentFolder;
use App\Domain\CollaborativeDocument\Repositories\CollaborativeDocumentRepositoryInterface;
use App\Domain\CollaborativeDocument\Repositories\DocumentFolderRepositoryInterface;
use App\Domain\CollaborativeDocument\Repositories\DocumentVersionRepositoryInterface;
use App\Domain\CollaborativeDocument\Repositories\DocumentCollaboratorRepositoryInterface;
use App\Domain\CollaborativeDocument\Repositories\DocumentCommentRepositoryInterface;
use App\Domain\CollaborativeDocument\ValueObjects\DocumentType;
use App\Domain\CollaborativeDocument\ValueObjects\DocumentPermission;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CollaborativeDocumentApplicationService
{
    public function __construct(
        private CollaborativeDocumentRepositoryInterface $documentRepository,
        private DocumentFolderRepositoryInterface $folderRepository,
        private DocumentVersionRepositoryInterface $versionRepository,
        private DocumentCollaboratorRepositoryInterface $collaboratorRepository,
        private DocumentCommentRepositoryInterface $commentRepository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // DOCUMENT USE CASES
    // =========================================================================

    /**
     * Create a new document.
     */
    public function createDocument(
        string $title,
        string $type,
        ?int $folderId = null,
    ): array {
        $userId = $this->authContext->getUserId();

        if (!$userId) {
            throw new InvalidArgumentException('User must be authenticated');
        }

        // Validate folder belongs to user if specified
        if ($folderId !== null) {
            $folder = $this->folderRepository->findById($folderId);
            if (!$folder || $folder->getOwnerId() !== $userId) {
                throw new InvalidArgumentException('Invalid folder');
            }
        }

        $document = CollaborativeDocument::create(
            title: $title,
            type: DocumentType::from($type),
            ownerId: $userId,
            parentFolderId: $folderId,
        );

        $saved = $this->documentRepository->save($document);

        return $this->documentRepository->findByIdWithRelations($saved->getId(), ['owner']);
    }

    /**
     * Get a document by ID with permission check.
     */
    public function getDocument(int $id): ?array
    {
        $userId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($id);

        if (!$document) {
            return null;
        }

        // Check access
        if (!$this->hasDocumentAccess($document, $userId)) {
            return null;
        }

        $result = $this->documentRepository->findByIdWithRelations($id, ['owner', 'collaborators', 'last_editor']);

        // Add user's permission level
        if ($document->getOwnerId() === $userId) {
            $result['user_permission'] = DocumentPermission::OWNER->value;
        } else {
            $permission = $this->collaboratorRepository->getPermission($id, $userId);
            $result['user_permission'] = $permission?->value ?? DocumentPermission::VIEW->value;
        }

        // Add stats
        $result['version_count'] = $this->versionRepository->countByDocument($id);
        $result['comment_count'] = $this->commentRepository->countByDocument($id);
        $result['unresolved_comments'] = $this->commentRepository->countUnresolvedThreads($id);

        return $result;
    }

    /**
     * List documents for the current user.
     */
    public function listDocuments(array $filters = [], int $perPage = 20, int $page = 1): PaginatedResult
    {
        $userId = $this->authContext->getUserId();

        // Include documents owned by user and shared with user
        $filters['shared_with_user'] = $userId;

        return $this->documentRepository->listDocuments(
            filters: $filters,
            perPage: $perPage,
            page: $page,
        );
    }

    /**
     * Get recent documents for the current user.
     */
    public function getRecentDocuments(int $limit = 10): array
    {
        $userId = $this->authContext->getUserId();
        $documents = $this->documentRepository->findRecentForUser($userId, $limit);

        return array_map(fn($doc) => $this->documentRepository->findByIdAsArray($doc->getId()), $documents);
    }

    /**
     * Update document metadata.
     */
    public function updateDocument(int $id, array $data): array
    {
        $userId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($id);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        if (!$this->canEditDocument($document, $userId)) {
            throw new InvalidArgumentException('Permission denied');
        }

        if (isset($data['title'])) {
            $document = $document->rename($data['title']);
        }

        if (array_key_exists('folder_id', $data)) {
            if ($data['folder_id'] !== null) {
                $folder = $this->folderRepository->findById($data['folder_id']);
                if (!$folder || $folder->getOwnerId() !== $document->getOwnerId()) {
                    throw new InvalidArgumentException('Invalid folder');
                }
            }
            $document = $document->move($data['folder_id']);
        }

        if (isset($data['is_template'])) {
            $document = $data['is_template']
                ? $document->markAsTemplate()
                : $document->unmarkAsTemplate();
        }

        $this->documentRepository->save($document);

        return $this->documentRepository->findByIdWithRelations($id, ['owner']);
    }

    /**
     * Delete a document.
     */
    public function deleteDocument(int $id): bool
    {
        $userId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($id);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        if ($document->getOwnerId() !== $userId) {
            throw new InvalidArgumentException('Only the owner can delete a document');
        }

        return DB::transaction(function () use ($id) {
            // Soft delete - keeps collaborators, versions, comments
            return $this->documentRepository->delete($id);
        });
    }

    /**
     * Duplicate a document.
     */
    public function duplicateDocument(int $id, ?string $title = null): array
    {
        $userId = $this->authContext->getUserId();
        $original = $this->documentRepository->findById($id);

        if (!$original) {
            throw new InvalidArgumentException('Document not found');
        }

        if (!$this->hasDocumentAccess($original, $userId)) {
            throw new InvalidArgumentException('Permission denied');
        }

        $newTitle = $title ?? $original->getTitle() . ' (Copy)';
        $duplicate = $original->duplicate($newTitle, $userId);
        $saved = $this->documentRepository->save($duplicate);

        return $this->documentRepository->findByIdWithRelations($saved->getId(), ['owner']);
    }

    /**
     * Create document from template.
     */
    public function createFromTemplate(int $templateId, string $title, ?int $folderId = null): array
    {
        $userId = $this->authContext->getUserId();
        $template = $this->documentRepository->findById($templateId);

        if (!$template || !$template->isTemplate()) {
            throw new InvalidArgumentException('Template not found');
        }

        $document = CollaborativeDocument::createFromTemplate(
            template: $template,
            title: $title,
            ownerId: $userId,
            parentFolderId: $folderId,
        );

        $saved = $this->documentRepository->save($document);

        return $this->documentRepository->findByIdWithRelations($saved->getId(), ['owner']);
    }

    /**
     * Search documents.
     */
    public function searchDocuments(string $query, int $limit = 50): array
    {
        $userId = $this->authContext->getUserId();
        return $this->documentRepository->search($query, $userId, $limit);
    }

    /**
     * Get document statistics for current user.
     */
    public function getStatistics(): array
    {
        $userId = $this->authContext->getUserId();
        return $this->documentRepository->getStatistics($userId);
    }

    /**
     * List templates.
     */
    public function listTemplates(?string $type = null): array
    {
        $userId = $this->authContext->getUserId();
        $docType = $type ? DocumentType::from($type) : null;
        $templates = $this->documentRepository->findTemplates($userId, $docType);

        return array_map(fn($t) => $this->documentRepository->findByIdAsArray($t->getId()), $templates);
    }

    // =========================================================================
    // FOLDER USE CASES
    // =========================================================================

    /**
     * Create a folder.
     */
    public function createFolder(string $name, ?int $parentId = null, ?string $color = null): array
    {
        $userId = $this->authContext->getUserId();

        if ($parentId !== null) {
            $parent = $this->folderRepository->findById($parentId);
            if (!$parent || $parent->getOwnerId() !== $userId) {
                throw new InvalidArgumentException('Invalid parent folder');
            }
        }

        // Check for duplicate name in same parent
        $existing = $this->folderRepository->findByNameInParent($name, $parentId, $userId);
        if ($existing) {
            throw new InvalidArgumentException('A folder with this name already exists');
        }

        $folder = DocumentFolder::create(
            name: $name,
            ownerId: $userId,
            parentId: $parentId,
            color: $color,
        );

        $saved = $this->folderRepository->save($folder);

        return $this->folderRepository->findByIdAsArray($saved->getId());
    }

    /**
     * Get folder tree.
     */
    public function getFolderTree(): array
    {
        $userId = $this->authContext->getUserId();
        return $this->folderRepository->getFolderTree($userId);
    }

    /**
     * Get folder contents (documents and subfolders).
     */
    public function getFolderContents(?int $folderId = null): array
    {
        $userId = $this->authContext->getUserId();

        // Verify folder ownership if specified
        if ($folderId !== null) {
            $folder = $this->folderRepository->findById($folderId);
            if (!$folder || $folder->getOwnerId() !== $userId) {
                throw new InvalidArgumentException('Folder not found');
            }
        }

        $subfolders = $this->folderRepository->findByParent($folderId, $userId);
        $documents = $this->documentRepository->findByFolder($folderId, $userId);

        return [
            'folder' => $folderId ? $this->folderRepository->findByIdAsArray($folderId) : null,
            'path' => $folderId ? $this->folderRepository->getFolderPath($folderId) : [],
            'subfolders' => array_map(fn($f) => $this->folderRepository->findByIdAsArray($f->getId()), $subfolders),
            'documents' => array_map(fn($d) => $this->documentRepository->findByIdAsArray($d->getId()), $documents),
        ];
    }

    /**
     * Update a folder.
     */
    public function updateFolder(int $id, array $data): array
    {
        $userId = $this->authContext->getUserId();
        $folder = $this->folderRepository->findById($id);

        if (!$folder || $folder->getOwnerId() !== $userId) {
            throw new InvalidArgumentException('Folder not found');
        }

        if (isset($data['name'])) {
            // Check for duplicate name
            $existing = $this->folderRepository->findByNameInParent($data['name'], $folder->getParentId(), $userId);
            if ($existing && $existing->getId() !== $id) {
                throw new InvalidArgumentException('A folder with this name already exists');
            }
            $folder = $folder->rename($data['name']);
        }

        if (array_key_exists('parent_id', $data)) {
            $newParentId = $data['parent_id'];

            // Prevent moving to self or descendant
            if ($newParentId !== null) {
                if ($newParentId === $id) {
                    throw new InvalidArgumentException('Cannot move folder into itself');
                }
                if ($this->folderRepository->isDescendantOf($newParentId, $id)) {
                    throw new InvalidArgumentException('Cannot move folder into its descendant');
                }
            }

            $folder = $folder->move($newParentId);
        }

        if (isset($data['color'])) {
            $folder = $folder->changeColor($data['color']);
        }

        $this->folderRepository->save($folder);

        return $this->folderRepository->findByIdAsArray($id);
    }

    /**
     * Delete a folder (moves contents to parent).
     */
    public function deleteFolder(int $id): bool
    {
        $userId = $this->authContext->getUserId();
        $folder = $this->folderRepository->findById($id);

        if (!$folder || $folder->getOwnerId() !== $userId) {
            throw new InvalidArgumentException('Folder not found');
        }

        return DB::transaction(function () use ($id, $folder, $userId) {
            $parentId = $folder->getParentId();

            // Move subfolders to parent
            $subfolders = $this->folderRepository->findByParent($id, $userId);
            foreach ($subfolders as $subfolder) {
                $subfolder = $subfolder->move($parentId);
                $this->folderRepository->save($subfolder);
            }

            // Move documents to parent
            $documents = $this->documentRepository->findByFolder($id, $userId);
            foreach ($documents as $doc) {
                $doc = $doc->move($parentId);
                $this->documentRepository->save($doc);
            }

            return $this->folderRepository->delete($id);
        });
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function hasDocumentAccess(CollaborativeDocument $document, ?int $userId): bool
    {
        if ($userId === null) {
            return $document->isPubliclyShared() && !$document->isShareLinkExpired();
        }

        if ($document->getOwnerId() === $userId) {
            return true;
        }

        return $this->collaboratorRepository->hasAccess($document->getId(), $userId);
    }

    private function canEditDocument(CollaborativeDocument $document, ?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        if ($document->getOwnerId() === $userId) {
            return true;
        }

        $permission = $this->collaboratorRepository->getPermission($document->getId(), $userId);
        return $permission?->canEdit() ?? false;
    }
}
