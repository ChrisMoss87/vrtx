<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Entities;

use App\Domain\CollaborativeDocument\ValueObjects\DocumentContent;
use App\Domain\CollaborativeDocument\ValueObjects\DocumentPermission;
use App\Domain\CollaborativeDocument\ValueObjects\DocumentType;
use App\Domain\CollaborativeDocument\ValueObjects\ShareSettings;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;
use InvalidArgumentException;

final class CollaborativeDocument implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private string $title,
        private DocumentType $type,
        private DocumentContent $content,
        private int $ownerId,
        private ?int $parentFolderId,
        private bool $isTemplate,
        private bool $isPubliclyShared,
        private ?ShareSettings $shareSettings,
        private int $currentVersion,
        private ?DateTimeImmutable $lastEditedAt,
        private ?int $lastEditedBy,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    /**
     * Create a new collaborative document.
     */
    public static function create(
        string $title,
        DocumentType $type,
        int $ownerId,
        ?int $parentFolderId = null,
    ): self {
        $title = trim($title);
        if (empty($title)) {
            throw new InvalidArgumentException('Document title cannot be empty');
        }

        if (mb_strlen($title) > 255) {
            throw new InvalidArgumentException('Document title cannot exceed 255 characters');
        }

        if ($ownerId <= 0) {
            throw new InvalidArgumentException('Owner ID must be positive');
        }

        return new self(
            id: null,
            title: $title,
            type: $type,
            content: DocumentContent::empty($type),
            ownerId: $ownerId,
            parentFolderId: $parentFolderId,
            isTemplate: false,
            isPubliclyShared: false,
            shareSettings: null,
            currentVersion: 1,
            lastEditedAt: null,
            lastEditedBy: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * Create a document from a template.
     */
    public static function createFromTemplate(
        self $template,
        string $title,
        int $ownerId,
        ?int $parentFolderId = null,
    ): self {
        if (!$template->isTemplate) {
            throw new InvalidArgumentException('Source document is not a template');
        }

        $title = trim($title);
        if (empty($title)) {
            throw new InvalidArgumentException('Document title cannot be empty');
        }

        if (mb_strlen($title) > 255) {
            throw new InvalidArgumentException('Document title cannot exceed 255 characters');
        }

        if ($ownerId <= 0) {
            throw new InvalidArgumentException('Owner ID must be positive');
        }

        return new self(
            id: null,
            title: $title,
            type: $template->type,
            content: $template->content,
            ownerId: $ownerId,
            parentFolderId: $parentFolderId,
            isTemplate: false,
            isPubliclyShared: false,
            shareSettings: null,
            currentVersion: 1,
            lastEditedAt: null,
            lastEditedBy: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $title,
        DocumentType $type,
        DocumentContent $content,
        int $ownerId,
        ?int $parentFolderId,
        bool $isTemplate,
        bool $isPubliclyShared,
        ?ShareSettings $shareSettings,
        int $currentVersion,
        ?DateTimeImmutable $lastEditedAt,
        ?int $lastEditedBy,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            id: $id,
            title: $title,
            type: $type,
            content: $content,
            ownerId: $ownerId,
            parentFolderId: $parentFolderId,
            isTemplate: $isTemplate,
            isPubliclyShared: $isPubliclyShared,
            shareSettings: $shareSettings,
            currentVersion: $currentVersion,
            lastEditedAt: $lastEditedAt,
            lastEditedBy: $lastEditedBy,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    /**
     * Rename the document.
     */
    public function rename(string $title): self
    {
        $title = trim($title);
        if (empty($title)) {
            throw new InvalidArgumentException('Document title cannot be empty');
        }

        if (mb_strlen($title) > 255) {
            throw new InvalidArgumentException('Document title cannot exceed 255 characters');
        }

        if ($this->title === $title) {
            return $this;
        }

        $new = clone $this;
        $new->title = $title;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    /**
     * Update the document content with new Y.js state.
     */
    public function updateContent(DocumentContent $content, int $userId): self
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }

        $new = clone $this;
        $new->content = $content;
        $new->lastEditedAt = new DateTimeImmutable();
        $new->lastEditedBy = $userId;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    /**
     * Move the document to a different folder.
     */
    public function move(?int $folderId): self
    {
        if ($this->parentFolderId === $folderId) {
            return $this;
        }

        $new = clone $this;
        $new->parentFolderId = $folderId;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    /**
     * Mark this document as a template.
     */
    public function markAsTemplate(): self
    {
        if ($this->isTemplate) {
            return $this;
        }

        $new = clone $this;
        $new->isTemplate = true;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    /**
     * Unmark this document as a template.
     */
    public function unmarkAsTemplate(): self
    {
        if (!$this->isTemplate) {
            return $this;
        }

        $new = clone $this;
        $new->isTemplate = false;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    /**
     * Enable link sharing.
     */
    public function enableLinkSharing(
        DocumentPermission $permission,
        ?string $password = null,
        ?DateTimeImmutable $expiresAt = null,
        bool $allowDownload = true,
        bool $requireEmail = false,
    ): self {
        $new = clone $this;
        $new->isPubliclyShared = true;
        $new->shareSettings = ShareSettings::create(
            permission: $permission,
            password: $password,
            expiresAt: $expiresAt,
            allowDownload: $allowDownload,
            requireEmail: $requireEmail,
        );
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    /**
     * Update link sharing settings.
     */
    public function updateLinkSharing(
        ?DocumentPermission $permission = null,
        ?string $password = null,
        ?DateTimeImmutable $expiresAt = null,
        ?bool $allowDownload = null,
        ?bool $requireEmail = null,
    ): self {
        if ($this->shareSettings === null) {
            throw new InvalidArgumentException('Link sharing is not enabled');
        }

        $new = clone $this;
        $settings = $new->shareSettings;

        if ($permission !== null) {
            $settings = $settings->withPermission($permission);
        }

        if ($password !== null) {
            $settings = $settings->withPassword($password);
        }

        if ($expiresAt !== null) {
            $settings = $settings->withExpiration($expiresAt);
        }

        $new->shareSettings = $settings;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    /**
     * Disable link sharing.
     */
    public function disableLinkSharing(): self
    {
        if (!$this->isPubliclyShared) {
            return $this;
        }

        $new = clone $this;
        $new->isPubliclyShared = false;
        $new->shareSettings = null;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    /**
     * Regenerate the share link token.
     */
    public function regenerateShareToken(): self
    {
        if ($this->shareSettings === null) {
            throw new InvalidArgumentException('Link sharing is not enabled');
        }

        $new = clone $this;
        $new->shareSettings = $this->shareSettings->regenerateToken();
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    /**
     * Increment the version number.
     */
    public function incrementVersion(): self
    {
        $new = clone $this;
        $new->currentVersion++;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    /**
     * Restore to a specific version's content.
     */
    public function restoreToVersion(DocumentContent $content, int $versionNumber, int $userId): self
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }

        $new = clone $this;
        $new->content = $content;
        $new->currentVersion = $versionNumber;
        $new->lastEditedAt = new DateTimeImmutable();
        $new->lastEditedBy = $userId;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    /**
     * Soft delete the document.
     */
    public function delete(): self
    {
        if ($this->deletedAt !== null) {
            return $this;
        }

        $new = clone $this;
        $new->deletedAt = new DateTimeImmutable();

        return $new;
    }

    /**
     * Restore a soft-deleted document.
     */
    public function restore(): self
    {
        if ($this->deletedAt === null) {
            return $this;
        }

        $new = clone $this;
        $new->deletedAt = null;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    /**
     * Duplicate the document.
     */
    public function duplicate(string $title, int $ownerId): self
    {
        $title = trim($title);
        if (empty($title)) {
            throw new InvalidArgumentException('Document title cannot be empty');
        }

        if ($ownerId <= 0) {
            throw new InvalidArgumentException('Owner ID must be positive');
        }

        return new self(
            id: null,
            title: $title,
            type: $this->type,
            content: $this->content,
            ownerId: $ownerId,
            parentFolderId: $this->parentFolderId,
            isTemplate: false,
            isPubliclyShared: false,
            shareSettings: null,
            currentVersion: 1,
            lastEditedAt: null,
            lastEditedBy: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    // Query methods
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function hasLinkSharing(): bool
    {
        return $this->isPubliclyShared && $this->shareSettings !== null;
    }

    public function isShareLinkExpired(): bool
    {
        return $this->shareSettings?->isExpired() ?? false;
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->ownerId === $userId;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getType(): DocumentType
    {
        return $this->type;
    }

    public function getContent(): DocumentContent
    {
        return $this->content;
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    public function getParentFolderId(): ?int
    {
        return $this->parentFolderId;
    }

    public function isTemplate(): bool
    {
        return $this->isTemplate;
    }

    public function isPubliclyShared(): bool
    {
        return $this->isPubliclyShared;
    }

    public function getShareSettings(): ?ShareSettings
    {
        return $this->shareSettings;
    }

    public function getCurrentVersion(): int
    {
        return $this->currentVersion;
    }

    public function getLastEditedAt(): ?DateTimeImmutable
    {
        return $this->lastEditedAt;
    }

    public function getLastEditedBy(): ?int
    {
        return $this->lastEditedBy;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function equals(\App\Domain\Shared\Contracts\Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        if ($this->id === null || $other->id === null) {
            return false;
        }

        return $this->id === $other->id;
    }
}
