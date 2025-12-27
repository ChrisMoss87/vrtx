<?php

declare(strict_types=1);

namespace App\Application\Services\CollaborativeDocument;

use App\Domain\CollaborativeDocument\Entities\DocumentCollaborator;
use App\Domain\CollaborativeDocument\Repositories\CollaborativeDocumentRepositoryInterface;
use App\Domain\CollaborativeDocument\Repositories\DocumentCollaboratorRepositoryInterface;
use App\Domain\CollaborativeDocument\ValueObjects\DocumentPermission;
use App\Domain\CollaborativeDocument\Events\DocumentShared;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use Illuminate\Contracts\Events\Dispatcher;
use DateTimeImmutable;
use InvalidArgumentException;

class DocumentSharingService
{
    public function __construct(
        private CollaborativeDocumentRepositoryInterface $documentRepository,
        private DocumentCollaboratorRepositoryInterface $collaboratorRepository,
        private UserRepositoryInterface $userRepository,
        private AuthContextInterface $authContext,
        private Dispatcher $eventDispatcher,
    ) {}

    // =========================================================================
    // COLLABORATOR USE CASES
    // =========================================================================

    /**
     * Add a collaborator to a document.
     */
    public function addCollaborator(int $documentId, int $userId, string $permission): array
    {
        $currentUserId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        // Only owner can add collaborators
        if ($document->getOwnerId() !== $currentUserId) {
            throw new InvalidArgumentException('Only the owner can add collaborators');
        }

        // Cannot add owner as collaborator
        if ($document->getOwnerId() === $userId) {
            throw new InvalidArgumentException('Cannot add document owner as collaborator');
        }

        // Check if user exists
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }

        // Check if already a collaborator
        $existing = $this->collaboratorRepository->findByDocumentAndUser($documentId, $userId);
        if ($existing) {
            throw new InvalidArgumentException('User is already a collaborator');
        }

        $collaborator = DocumentCollaborator::create(
            documentId: $documentId,
            userId: $userId,
            permission: DocumentPermission::from($permission),
        );

        $saved = $this->collaboratorRepository->save($collaborator);

        // Dispatch event
        $this->eventDispatcher->dispatch(new DocumentShared(
            documentId: $documentId,
            sharedWithUserId: $userId,
            permission: $permission,
            sharedByUserId: $currentUserId,
        ));

        return $this->collaboratorRepository->findByIdAsArray($saved->getId());
    }

    /**
     * Remove a collaborator from a document.
     */
    public function removeCollaborator(int $documentId, int $userId): bool
    {
        $currentUserId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        // Owner can remove anyone, collaborators can only remove themselves
        if ($document->getOwnerId() !== $currentUserId && $userId !== $currentUserId) {
            throw new InvalidArgumentException('Permission denied');
        }

        $collaborator = $this->collaboratorRepository->findByDocumentAndUser($documentId, $userId);

        if (!$collaborator) {
            throw new InvalidArgumentException('Collaborator not found');
        }

        return $this->collaboratorRepository->delete($collaborator->getId());
    }

    /**
     * Update a collaborator's permission.
     */
    public function updateCollaboratorPermission(int $documentId, int $userId, string $permission): array
    {
        $currentUserId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        // Only owner can update permissions
        if ($document->getOwnerId() !== $currentUserId) {
            throw new InvalidArgumentException('Only the owner can update permissions');
        }

        $collaborator = $this->collaboratorRepository->findByDocumentAndUser($documentId, $userId);

        if (!$collaborator) {
            throw new InvalidArgumentException('Collaborator not found');
        }

        $collaborator = $collaborator->updatePermission(DocumentPermission::from($permission));
        $this->collaboratorRepository->save($collaborator);

        return $this->collaboratorRepository->findByIdAsArray($collaborator->getId());
    }

    /**
     * List collaborators for a document.
     */
    public function listCollaborators(int $documentId): array
    {
        $currentUserId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        // Check access
        $hasAccess = $document->getOwnerId() === $currentUserId
            || $this->collaboratorRepository->hasAccess($documentId, $currentUserId);

        if (!$hasAccess) {
            throw new InvalidArgumentException('Permission denied');
        }

        return $this->collaboratorRepository->findByDocumentWithUserDetails($documentId);
    }

    /**
     * Get active collaborators (currently viewing).
     */
    public function getActiveCollaborators(int $documentId): array
    {
        $activeCollaborators = $this->collaboratorRepository->findByDocumentWithUserDetails($documentId);

        return array_filter($activeCollaborators, fn($c) => $c['is_currently_viewing']);
    }

    // =========================================================================
    // LINK SHARING USE CASES
    // =========================================================================

    /**
     * Enable link sharing for a document.
     */
    public function enableLinkSharing(
        int $documentId,
        string $permission,
        ?string $password = null,
        ?string $expiresAt = null,
        bool $allowDownload = true,
        bool $requireEmail = false,
    ): array {
        $currentUserId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        // Only owner can manage link sharing
        if ($document->getOwnerId() !== $currentUserId) {
            throw new InvalidArgumentException('Only the owner can manage link sharing');
        }

        $expiresAtDate = $expiresAt ? new DateTimeImmutable($expiresAt) : null;

        $document = $document->enableLinkSharing(
            permission: DocumentPermission::from($permission),
            password: $password,
            expiresAt: $expiresAtDate,
            allowDownload: $allowDownload,
            requireEmail: $requireEmail,
        );

        $this->documentRepository->save($document);

        return [
            'enabled' => true,
            'token' => $document->getShareSettings()->getToken(),
            'permission' => $document->getShareSettings()->getPermission()->value,
            'has_password' => $document->getShareSettings()->hasPassword(),
            'expires_at' => $document->getShareSettings()->getExpiresAt()?->format('Y-m-d H:i:s'),
            'allow_download' => $document->getShareSettings()->allowsDownload(),
            'require_email' => $document->getShareSettings()->requiresEmail(),
            'share_url' => $this->generateShareUrl($document->getShareSettings()->getToken()),
        ];
    }

    /**
     * Update link sharing settings.
     */
    public function updateLinkSharing(
        int $documentId,
        ?string $permission = null,
        ?string $password = null,
        ?string $expiresAt = null,
        ?bool $allowDownload = null,
        ?bool $requireEmail = null,
    ): array {
        $currentUserId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        if ($document->getOwnerId() !== $currentUserId) {
            throw new InvalidArgumentException('Only the owner can manage link sharing');
        }

        if (!$document->hasLinkSharing()) {
            throw new InvalidArgumentException('Link sharing is not enabled');
        }

        $document = $document->updateLinkSharing(
            permission: $permission ? DocumentPermission::from($permission) : null,
            password: $password,
            expiresAt: $expiresAt ? new DateTimeImmutable($expiresAt) : null,
        );

        $this->documentRepository->save($document);

        return [
            'enabled' => true,
            'token' => $document->getShareSettings()->getToken(),
            'permission' => $document->getShareSettings()->getPermission()->value,
            'has_password' => $document->getShareSettings()->hasPassword(),
            'expires_at' => $document->getShareSettings()->getExpiresAt()?->format('Y-m-d H:i:s'),
            'allow_download' => $document->getShareSettings()->allowsDownload(),
            'require_email' => $document->getShareSettings()->requiresEmail(),
            'share_url' => $this->generateShareUrl($document->getShareSettings()->getToken()),
        ];
    }

    /**
     * Disable link sharing.
     */
    public function disableLinkSharing(int $documentId): bool
    {
        $currentUserId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        if ($document->getOwnerId() !== $currentUserId) {
            throw new InvalidArgumentException('Only the owner can manage link sharing');
        }

        if (!$document->hasLinkSharing()) {
            return true;
        }

        $document = $document->disableLinkSharing();
        $this->documentRepository->save($document);

        return true;
    }

    /**
     * Regenerate share link token.
     */
    public function regenerateShareToken(int $documentId): array
    {
        $currentUserId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        if ($document->getOwnerId() !== $currentUserId) {
            throw new InvalidArgumentException('Only the owner can manage link sharing');
        }

        if (!$document->hasLinkSharing()) {
            throw new InvalidArgumentException('Link sharing is not enabled');
        }

        $document = $document->regenerateShareToken();
        $this->documentRepository->save($document);

        return [
            'token' => $document->getShareSettings()->getToken(),
            'share_url' => $this->generateShareUrl($document->getShareSettings()->getToken()),
        ];
    }

    /**
     * Access a document via share link.
     */
    public function accessViaShareLink(string $token, ?string $password = null): array
    {
        $document = $this->documentRepository->findByShareToken($token);

        if (!$document) {
            throw new InvalidArgumentException('Invalid or expired share link');
        }

        if ($document->isShareLinkExpired()) {
            throw new InvalidArgumentException('Share link has expired');
        }

        $shareSettings = $document->getShareSettings();

        if ($shareSettings->hasPassword() && !$shareSettings->verifyPassword($password ?? '')) {
            throw new InvalidArgumentException('Invalid password');
        }

        return [
            'document_id' => $document->getId(),
            'title' => $document->getTitle(),
            'type' => $document->getType()->value,
            'permission' => $shareSettings->getPermission()->value,
            'allow_download' => $shareSettings->allowsDownload(),
            'yjs_state' => $document->getContent()->getYjsState(),
        ];
    }

    /**
     * Get link sharing status.
     */
    public function getLinkSharingStatus(int $documentId): array
    {
        $currentUserId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            throw new InvalidArgumentException('Document not found');
        }

        // Check access
        $hasAccess = $document->getOwnerId() === $currentUserId
            || $this->collaboratorRepository->hasAccess($documentId, $currentUserId);

        if (!$hasAccess) {
            throw new InvalidArgumentException('Permission denied');
        }

        if (!$document->hasLinkSharing()) {
            return ['enabled' => false];
        }

        $shareSettings = $document->getShareSettings();

        return [
            'enabled' => true,
            'token' => $document->getOwnerId() === $currentUserId ? $shareSettings->getToken() : null,
            'permission' => $shareSettings->getPermission()->value,
            'has_password' => $shareSettings->hasPassword(),
            'expires_at' => $shareSettings->getExpiresAt()?->format('Y-m-d H:i:s'),
            'is_expired' => $shareSettings->isExpired(),
            'allow_download' => $shareSettings->allowsDownload(),
            'require_email' => $shareSettings->requiresEmail(),
            'share_url' => $document->getOwnerId() === $currentUserId
                ? $this->generateShareUrl($shareSettings->getToken())
                : null,
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function generateShareUrl(string $token): string
    {
        $baseUrl = config('app.frontend_url', config('app.url'));
        return "{$baseUrl}/d/{$token}";
    }
}
