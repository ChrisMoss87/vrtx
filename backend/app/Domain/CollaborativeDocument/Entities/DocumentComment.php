<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Entities;

use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;
use InvalidArgumentException;

final class DocumentComment implements Entity
{
    private function __construct(
        private ?int $id,
        private int $documentId,
        private ?int $threadId,
        private int $userId,
        private string $content,
        private ?array $selectionRange,
        private bool $isResolved,
        private ?int $resolvedBy,
        private ?DateTimeImmutable $resolvedAt,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    /**
     * Create a new comment thread (top-level comment).
     */
    public static function createThread(
        int $documentId,
        int $userId,
        string $content,
        ?array $selectionRange = null,
    ): self {
        if ($documentId <= 0) {
            throw new InvalidArgumentException('Document ID must be positive');
        }

        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }

        $content = trim($content);
        if (empty($content)) {
            throw new InvalidArgumentException('Comment content cannot be empty');
        }

        if (mb_strlen($content) > 10000) {
            throw new InvalidArgumentException('Comment content cannot exceed 10000 characters');
        }

        self::validateSelectionRange($selectionRange);

        return new self(
            id: null,
            documentId: $documentId,
            threadId: null,
            userId: $userId,
            content: $content,
            selectionRange: $selectionRange,
            isResolved: false,
            resolvedBy: null,
            resolvedAt: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * Create a reply to an existing thread.
     */
    public static function createReply(
        int $documentId,
        int $threadId,
        int $userId,
        string $content,
    ): self {
        if ($documentId <= 0) {
            throw new InvalidArgumentException('Document ID must be positive');
        }

        if ($threadId <= 0) {
            throw new InvalidArgumentException('Thread ID must be positive');
        }

        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }

        $content = trim($content);
        if (empty($content)) {
            throw new InvalidArgumentException('Comment content cannot be empty');
        }

        if (mb_strlen($content) > 10000) {
            throw new InvalidArgumentException('Comment content cannot exceed 10000 characters');
        }

        return new self(
            id: null,
            documentId: $documentId,
            threadId: $threadId,
            userId: $userId,
            content: $content,
            selectionRange: null,
            isResolved: false,
            resolvedBy: null,
            resolvedAt: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $documentId,
        ?int $threadId,
        int $userId,
        string $content,
        ?array $selectionRange,
        bool $isResolved,
        ?int $resolvedBy,
        ?DateTimeImmutable $resolvedAt,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            id: $id,
            documentId: $documentId,
            threadId: $threadId,
            userId: $userId,
            content: $content,
            selectionRange: $selectionRange,
            isResolved: $isResolved,
            resolvedBy: $resolvedBy,
            resolvedAt: $resolvedAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    private static function validateSelectionRange(?array $range): void
    {
        if ($range === null) {
            return;
        }

        if (!isset($range['from']) || !isset($range['to'])) {
            throw new InvalidArgumentException('Selection range must have "from" and "to" keys');
        }

        if (!is_int($range['from']) || !is_int($range['to'])) {
            throw new InvalidArgumentException('Selection range "from" and "to" must be integers');
        }

        if ($range['from'] < 0 || $range['to'] < 0) {
            throw new InvalidArgumentException('Selection range values must be non-negative');
        }

        if ($range['from'] > $range['to']) {
            throw new InvalidArgumentException('Selection range "from" cannot be greater than "to"');
        }
    }

    public function edit(string $content): self
    {
        $content = trim($content);
        if (empty($content)) {
            throw new InvalidArgumentException('Comment content cannot be empty');
        }

        if (mb_strlen($content) > 10000) {
            throw new InvalidArgumentException('Comment content cannot exceed 10000 characters');
        }

        if ($this->content === $content) {
            return $this;
        }

        $new = clone $this;
        $new->content = $content;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function resolve(int $userId): self
    {
        if ($this->threadId !== null) {
            throw new InvalidArgumentException('Only thread starters can be resolved');
        }

        if ($this->isResolved) {
            return $this;
        }

        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }

        $new = clone $this;
        $new->isResolved = true;
        $new->resolvedBy = $userId;
        $new->resolvedAt = new DateTimeImmutable();
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function reopen(): self
    {
        if ($this->threadId !== null) {
            throw new InvalidArgumentException('Only thread starters can be reopened');
        }

        if (!$this->isResolved) {
            return $this;
        }

        $new = clone $this;
        $new->isResolved = false;
        $new->resolvedBy = null;
        $new->resolvedAt = null;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function delete(): self
    {
        if ($this->deletedAt !== null) {
            return $this;
        }

        $new = clone $this;
        $new->deletedAt = new DateTimeImmutable();

        return $new;
    }

    public function restore(): self
    {
        if ($this->deletedAt === null) {
            return $this;
        }

        $new = clone $this;
        $new->deletedAt = null;

        return $new;
    }

    public function isThreadStarter(): bool
    {
        return $this->threadId === null;
    }

    public function isReply(): bool
    {
        return $this->threadId !== null;
    }

    public function hasSelectionRange(): bool
    {
        return $this->selectionRange !== null;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
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

    public function getThreadId(): ?int
    {
        return $this->threadId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getSelectionRange(): ?array
    {
        return $this->selectionRange;
    }

    public function isResolved(): bool
    {
        return $this->isResolved;
    }

    public function getResolvedBy(): ?int
    {
        return $this->resolvedBy;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
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
