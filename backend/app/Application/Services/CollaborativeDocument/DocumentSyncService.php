<?php

declare(strict_types=1);

namespace App\Application\Services\CollaborativeDocument;

use App\Domain\CollaborativeDocument\Entities\DocumentVersion;
use App\Domain\CollaborativeDocument\Repositories\CollaborativeDocumentRepositoryInterface;
use App\Domain\CollaborativeDocument\Repositories\DocumentVersionRepositoryInterface;
use App\Domain\CollaborativeDocument\Repositories\DocumentCollaboratorRepositoryInterface;
use App\Domain\CollaborativeDocument\ValueObjects\DocumentContent;
use App\Domain\CollaborativeDocument\ValueObjects\CursorPosition;
use App\Domain\CollaborativeDocument\Events\DocumentUpdated;
use App\Domain\CollaborativeDocument\Events\CollaboratorJoined;
use App\Domain\CollaborativeDocument\Events\CollaboratorLeft;
use App\Domain\CollaborativeDocument\Events\VersionCreated;
use App\Domain\Shared\Contracts\AuthContextInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Events\Dispatcher;
use InvalidArgumentException;

class DocumentSyncService
{
    private const AUTO_SAVE_INTERVAL_SECONDS = 300; // 5 minutes
    private const MAX_AUTO_SAVE_VERSIONS = 50;

    public function __construct(
        private CollaborativeDocumentRepositoryInterface $documentRepository,
        private DocumentVersionRepositoryInterface $versionRepository,
        private DocumentCollaboratorRepositoryInterface $collaboratorRepository,
        private AuthContextInterface $authContext,
        private Dispatcher $eventDispatcher,
    ) {}

    // =========================================================================
    // SYNC USE CASES
    // =========================================================================

    /**
     * Process a Y.js update from a client.
     */
    public function processUpdate(int $documentId, string $yjsUpdate): array
    {
        $userId = $this->authContext->getUserId();

        if (!$userId) {
            throw new InvalidArgumentException('User must be authenticated');
        }

        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        // Check edit permission
        if (!$this->canEdit($documentId, $userId)) {
            throw new InvalidArgumentException('Permission denied');
        }

        // Merge the Y.js update with existing state
        $currentState = $document->getContent()->getYjsState();
        $mergedState = $this->mergeYjsUpdates($currentState, $yjsUpdate);

        // Create new content value object
        $newContent = DocumentContent::fromYjsState($mergedState);

        // Update document
        $document = $document->updateContent($newContent, $userId);
        $this->documentRepository->save($document);

        // Broadcast update to other collaborators
        $this->eventDispatcher->dispatch(new DocumentUpdated(
            documentId: $documentId,
            userId: $userId,
            yjsUpdate: $yjsUpdate,
        ));

        return [
            'success' => true,
            'document_id' => $documentId,
            'version' => $document->getCurrentVersion(),
        ];
    }

    /**
     * Get the current document state.
     */
    public function getDocumentState(int $documentId): array
    {
        $userId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        // Check access
        $hasAccess = $document->getOwnerId() === $userId
            || $this->collaboratorRepository->hasAccess($documentId, $userId);

        if (!$hasAccess) {
            throw new InvalidArgumentException('Permission denied');
        }

        return [
            'document_id' => $documentId,
            'yjs_state' => $document->getContent()->getYjsState(),
            'version' => $document->getCurrentVersion(),
            'last_edited_at' => $document->getLastEditedAt()?->format('Y-m-d H:i:s'),
            'last_edited_by' => $document->getLastEditedBy(),
        ];
    }

    /**
     * Join a collaboration session.
     */
    public function joinSession(int $documentId): array
    {
        $userId = $this->authContext->getUserId();
        $userName = $this->authContext->getUserName() ?? 'Unknown';

        if (!$userId) {
            throw new InvalidArgumentException('User must be authenticated');
        }

        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        // Check access
        $isOwner = $document->getOwnerId() === $userId;
        $collaborator = $this->collaboratorRepository->findByDocumentAndUser($documentId, $userId);

        if (!$isOwner && !$collaborator) {
            throw new InvalidArgumentException('Permission denied');
        }

        // Mark as active
        if ($collaborator) {
            $collaborator = $collaborator->markActive();
            $this->collaboratorRepository->save($collaborator);
        }

        // Generate user color based on user ID
        $userColor = $this->generateUserColor($userId);

        // Broadcast join event
        $this->eventDispatcher->dispatch(new CollaboratorJoined(
            documentId: $documentId,
            userId: $userId,
            userName: $userName,
            userColor: $userColor,
        ));

        // Get current active collaborators
        $activeCollaborators = $this->collaboratorRepository->findByDocumentWithUserDetails($documentId);

        return [
            'document_id' => $documentId,
            'user_id' => $userId,
            'user_name' => $userName,
            'user_color' => $userColor,
            'permission' => $isOwner ? 'owner' : $collaborator->getPermission()->value,
            'yjs_state' => $document->getContent()->getYjsState(),
            'version' => $document->getCurrentVersion(),
            'active_collaborators' => array_filter($activeCollaborators, fn($c) => $c['is_currently_viewing']),
        ];
    }

    /**
     * Leave a collaboration session.
     */
    public function leaveSession(int $documentId): array
    {
        $userId = $this->authContext->getUserId();

        if (!$userId) {
            throw new InvalidArgumentException('User must be authenticated');
        }

        $collaborator = $this->collaboratorRepository->findByDocumentAndUser($documentId, $userId);

        if ($collaborator) {
            $collaborator = $collaborator->markInactive();
            $this->collaboratorRepository->save($collaborator);
        }

        // Broadcast leave event
        $this->eventDispatcher->dispatch(new CollaboratorLeft(
            documentId: $documentId,
            userId: $userId,
        ));

        return [
            'success' => true,
            'document_id' => $documentId,
        ];
    }

    /**
     * Update cursor position.
     */
    public function updateCursor(int $documentId, array $position): array
    {
        $userId = $this->authContext->getUserId();

        if (!$userId) {
            throw new InvalidArgumentException('User must be authenticated');
        }

        $collaborator = $this->collaboratorRepository->findByDocumentAndUser($documentId, $userId);

        if (!$collaborator) {
            // Owner might not have a collaborator record
            $document = $this->documentRepository->findById($documentId);
            if (!$document || $document->getOwnerId() !== $userId) {
                throw new InvalidArgumentException('Not a collaborator');
            }
            return ['success' => true]; // Owner cursor is handled differently
        }

        $cursorPosition = isset($position['selection'])
            ? CursorPosition::withSelection(
                line: $position['line'],
                column: $position['column'],
                selectionStartLine: $position['selection']['start']['line'],
                selectionStartColumn: $position['selection']['start']['column'],
                selectionEndLine: $position['selection']['end']['line'],
                selectionEndColumn: $position['selection']['end']['column'],
            )
            : CursorPosition::at($position['line'], $position['column']);

        $collaborator = $collaborator->updateCursor($cursorPosition);
        $this->collaboratorRepository->save($collaborator);

        return [
            'success' => true,
            'document_id' => $documentId,
        ];
    }

    // =========================================================================
    // VERSION USE CASES
    // =========================================================================

    /**
     * Create an auto-save version.
     */
    public function createAutoSaveVersion(int $documentId): ?array
    {
        $userId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($documentId);

        if (!$document || !$userId) {
            return null;
        }

        // Get latest version
        $latestVersion = $this->versionRepository->getLatestVersion($documentId);
        $versionNumber = $latestVersion ? $latestVersion->getVersionNumber() + 1 : 1;

        $version = DocumentVersion::createAutoSave(
            documentId: $documentId,
            versionNumber: $versionNumber,
            content: $document->getContent(),
            createdBy: $userId,
        );

        $saved = $this->versionRepository->save($version);

        // Increment document version
        $document = $document->incrementVersion();
        $this->documentRepository->save($document);

        // Clean up old auto-saves
        $this->versionRepository->deleteOldAutoSaves($documentId, self::MAX_AUTO_SAVE_VERSIONS);

        return $this->versionRepository->findByIdAsArray($saved->getId());
    }

    /**
     * Create a named version.
     */
    public function createNamedVersion(int $documentId, string $label): array
    {
        $userId = $this->authContext->getUserId();

        if (!$userId) {
            throw new InvalidArgumentException('User must be authenticated');
        }

        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        if (!$this->canEdit($documentId, $userId)) {
            throw new InvalidArgumentException('Permission denied');
        }

        $versionNumber = $this->versionRepository->getLatestVersionNumber($documentId) + 1;

        $version = DocumentVersion::createNamedVersion(
            documentId: $documentId,
            versionNumber: $versionNumber,
            label: $label,
            content: $document->getContent(),
            createdBy: $userId,
        );

        $saved = $this->versionRepository->save($version);

        // Increment document version
        $document = $document->incrementVersion();
        $this->documentRepository->save($document);

        // Dispatch event
        $this->eventDispatcher->dispatch(VersionCreated::fromVersion($saved));

        return $this->versionRepository->findByIdAsArray($saved->getId());
    }

    /**
     * Restore a document to a specific version.
     */
    public function restoreVersion(int $documentId, int $versionNumber): array
    {
        $userId = $this->authContext->getUserId();

        if (!$userId) {
            throw new InvalidArgumentException('User must be authenticated');
        }

        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        if (!$this->canEdit($documentId, $userId)) {
            throw new InvalidArgumentException('Permission denied');
        }

        $version = $this->versionRepository->findByDocumentAndVersion($documentId, $versionNumber);

        if (!$version) {
            throw new InvalidArgumentException('Version not found');
        }

        // Create a new version before restoring (to preserve current state)
        $this->createAutoSaveVersion($documentId);

        // Restore content
        $document = $document->restoreToVersion($version->getContent(), $versionNumber, $userId);
        $this->documentRepository->save($document);

        return $this->documentRepository->findByIdAsArray($documentId);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function canEdit(int $documentId, int $userId): bool
    {
        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            return false;
        }

        if ($document->getOwnerId() === $userId) {
            return true;
        }

        $permission = $this->collaboratorRepository->getPermission($documentId, $userId);
        return $permission?->canEdit() ?? false;
    }

    /**
     * Merge Y.js updates.
     * In production, this would use actual Y.js merge logic via Node.js sidecar or PHP extension.
     * For now, we just append the update.
     */
    private function mergeYjsUpdates(string $currentState, string $newUpdate): string
    {
        // TODO: Implement proper Y.js merge via Node.js sidecar
        // For now, just store the new update as the state
        // The frontend handles the actual CRDT merge
        return $newUpdate;
    }

    /**
     * Generate a consistent color for a user.
     */
    private function generateUserColor(int $userId): string
    {
        $colors = [
            '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
            '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9',
            '#F8B500', '#00CED1', '#FF69B4', '#32CD32', '#FF7F50',
        ];

        return $colors[$userId % count($colors)];
    }
}
