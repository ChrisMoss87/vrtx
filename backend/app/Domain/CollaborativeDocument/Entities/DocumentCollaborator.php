<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Entities;

use App\Domain\CollaborativeDocument\ValueObjects\CursorPosition;
use App\Domain\CollaborativeDocument\ValueObjects\DocumentPermission;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;
use InvalidArgumentException;

final class DocumentCollaborator implements Entity
{
    private function __construct(
        private ?int $id,
        private int $documentId,
        private int $userId,
        private DocumentPermission $permission,
        private ?CursorPosition $cursorPosition,
        private bool $isCurrentlyViewing,
        private ?DateTimeImmutable $lastActiveAt,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $documentId,
        int $userId,
        DocumentPermission $permission,
    ): self {
        if ($documentId <= 0) {
            throw new InvalidArgumentException('Document ID must be positive');
        }

        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }

        if ($permission === DocumentPermission::OWNER) {
            throw new InvalidArgumentException('Cannot create collaborator with owner permission');
        }

        return new self(
            id: null,
            documentId: $documentId,
            userId: $userId,
            permission: $permission,
            cursorPosition: null,
            isCurrentlyViewing: false,
            lastActiveAt: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $documentId,
        int $userId,
        DocumentPermission $permission,
        ?CursorPosition $cursorPosition,
        bool $isCurrentlyViewing,
        ?DateTimeImmutable $lastActiveAt,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            documentId: $documentId,
            userId: $userId,
            permission: $permission,
            cursorPosition: $cursorPosition,
            isCurrentlyViewing: $isCurrentlyViewing,
            lastActiveAt: $lastActiveAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function updatePermission(DocumentPermission $permission): self
    {
        if ($permission === DocumentPermission::OWNER) {
            throw new InvalidArgumentException('Cannot set collaborator to owner permission');
        }

        if ($this->permission === $permission) {
            return $this;
        }

        $new = clone $this;
        $new->permission = $permission;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function updateCursor(CursorPosition $cursor): self
    {
        $new = clone $this;
        $new->cursorPosition = $cursor;
        $new->lastActiveAt = new DateTimeImmutable();
        $new->isCurrentlyViewing = true;

        return $new;
    }

    public function clearCursor(): self
    {
        if ($this->cursorPosition === null) {
            return $this;
        }

        $new = clone $this;
        $new->cursorPosition = null;

        return $new;
    }

    public function markActive(): self
    {
        $new = clone $this;
        $new->isCurrentlyViewing = true;
        $new->lastActiveAt = new DateTimeImmutable();

        return $new;
    }

    public function markInactive(): self
    {
        if (!$this->isCurrentlyViewing) {
            return $this;
        }

        $new = clone $this;
        $new->isCurrentlyViewing = false;
        $new->cursorPosition = null;

        return $new;
    }

    public function canView(): bool
    {
        return $this->permission->canView();
    }

    public function canComment(): bool
    {
        return $this->permission->canComment();
    }

    public function canEdit(): bool
    {
        return $this->permission->canEdit();
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocumentId(): int
    {
        return $this->documentId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getPermission(): DocumentPermission
    {
        return $this->permission;
    }

    public function getCursorPosition(): ?CursorPosition
    {
        return $this->cursorPosition;
    }

    public function isCurrentlyViewing(): bool
    {
        return $this->isCurrentlyViewing;
    }

    public function getLastActiveAt(): ?DateTimeImmutable
    {
        return $this->lastActiveAt;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function equals(Entity $other): bool
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
