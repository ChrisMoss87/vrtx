<?php

declare(strict_types=1);

namespace App\Domain\CMS\Entities;

use App\Domain\CMS\ValueObjects\CommentStatus;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class CmsComment implements Entity
{
    private function __construct(
        private ?int $id,
        private int $pageId,
        private ?int $parentId,
        private ?int $userId,
        private ?string $authorName,
        private ?string $authorEmail,
        private ?string $authorUrl,
        private string $content,
        private CommentStatus $status,
        private ?string $ipAddress,
        private ?string $userAgent,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    public static function createAuthenticated(
        int $pageId,
        int $userId,
        string $content,
        ?int $parentId = null,
    ): self {
        return new self(
            id: null,
            pageId: $pageId,
            parentId: $parentId,
            userId: $userId,
            authorName: null,
            authorEmail: null,
            authorUrl: null,
            content: $content,
            status: CommentStatus::APPROVED,
            ipAddress: null,
            userAgent: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    public static function createGuest(
        int $pageId,
        string $authorName,
        string $authorEmail,
        string $content,
        ?string $authorUrl = null,
        ?int $parentId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): self {
        return new self(
            id: null,
            pageId: $pageId,
            parentId: $parentId,
            userId: null,
            authorName: $authorName,
            authorEmail: $authorEmail,
            authorUrl: $authorUrl,
            content: $content,
            status: CommentStatus::PENDING,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $pageId,
        ?int $parentId,
        ?int $userId,
        ?string $authorName,
        ?string $authorEmail,
        ?string $authorUrl,
        string $content,
        CommentStatus $status,
        ?string $ipAddress,
        ?string $userAgent,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            id: $id,
            pageId: $pageId,
            parentId: $parentId,
            userId: $userId,
            authorName: $authorName,
            authorEmail: $authorEmail,
            authorUrl: $authorUrl,
            content: $content,
            status: $status,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    public function getId(): ?int { return $this->id; }
    public function getPageId(): int { return $this->pageId; }
    public function getParentId(): ?int { return $this->parentId; }
    public function getUserId(): ?int { return $this->userId; }
    public function getAuthorName(): ?string { return $this->authorName; }
    public function getAuthorEmail(): ?string { return $this->authorEmail; }
    public function getAuthorUrl(): ?string { return $this->authorUrl; }
    public function getContent(): string { return $this->content; }
    public function getStatus(): CommentStatus { return $this->status; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }
    public function getDeletedAt(): ?DateTimeImmutable { return $this->deletedAt; }

    public function getDisplayName(): string
    {
        return $this->authorName ?? 'Anonymous';
    }

    public function isGuest(): bool
    {
        return $this->userId === null;
    }

    public function isReply(): bool
    {
        return $this->parentId !== null;
    }

    public function approve(): void
    {
        $this->status = CommentStatus::APPROVED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markAsSpam(): void
    {
        $this->status = CommentStatus::SPAM;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function trash(): void
    {
        $this->status = CommentStatus::TRASH;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->status = CommentStatus::PENDING;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function edit(string $content): void
    {
        $this->content = $content;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function delete(): void
    {
        $this->deletedAt = new DateTimeImmutable();
    }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->id !== null
            && $other->id !== null
            && $this->id === $other->id;
    }
}
