<?php

declare(strict_types=1);

namespace App\Domain\Chat\Entities;

use App\Domain\Chat\ValueObjects\MessageType;
use App\Domain\Chat\ValueObjects\SenderType;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;
use InvalidArgumentException;

final class ChatMessage implements Entity
{
    private function __construct(
        private ?int $id,
        private int $conversationId,
        private SenderType $senderType,
        private ?int $senderId,
        private string $content,
        private MessageType $contentType,
        private ?array $attachments,
        private ?array $metadata,
        private bool $isInternal,
        private ?DateTimeImmutable $readAt,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $conversationId,
        SenderType $senderType,
        string $content,
        ?int $senderId = null,
        MessageType $contentType = MessageType::TEXT,
        ?array $attachments = null,
        ?array $metadata = null,
        bool $isInternal = false,
    ): self {
        if ($conversationId <= 0) {
            throw new InvalidArgumentException('Conversation ID must be positive');
        }

        if (empty(trim($content))) {
            throw new InvalidArgumentException('Message content cannot be empty');
        }

        if ($senderId !== null && $senderId <= 0) {
            throw new InvalidArgumentException('Sender ID must be positive');
        }

        // Validate that agent and visitor messages have sender IDs
        if (($senderType->isAgent() || $senderType->isVisitor()) && $senderId === null) {
            throw new InvalidArgumentException(
                sprintf('%s messages must have a sender ID', $senderType->label())
            );
        }

        // System messages should not have sender IDs
        if ($senderType->isSystem() && $senderId !== null) {
            throw new InvalidArgumentException('System messages cannot have a sender ID');
        }

        return new self(
            id: null,
            conversationId: $conversationId,
            senderType: $senderType,
            senderId: $senderId,
            content: $content,
            contentType: $contentType,
            attachments: $attachments,
            metadata: $metadata,
            isInternal: $isInternal,
            readAt: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $conversationId,
        SenderType $senderType,
        ?int $senderId,
        string $content,
        MessageType $contentType,
        ?array $attachments,
        ?array $metadata,
        bool $isInternal,
        ?DateTimeImmutable $readAt,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            conversationId: $conversationId,
            senderType: $senderType,
            senderId: $senderId,
            content: $content,
            contentType: $contentType,
            attachments: $attachments,
            metadata: $metadata,
            isInternal: $isInternal,
            readAt: $readAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function markAsRead(): self
    {
        if ($this->readAt !== null) {
            return $this;
        }

        $new = clone $this;
        $new->readAt = new DateTimeImmutable();
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function updateContent(string $content): self
    {
        if (empty(trim($content))) {
            throw new InvalidArgumentException('Message content cannot be empty');
        }

        if ($this->content === $content) {
            return $this;
        }

        $new = clone $this;
        $new->content = $content;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function addAttachment(array $attachment): self
    {
        if (!$this->contentType->isAttachment()) {
            throw new InvalidArgumentException('Can only add attachments to image or file messages');
        }

        $attachments = $this->attachments ?? [];
        $attachments[] = $attachment;

        $new = clone $this;
        $new->attachments = $attachments;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function updateMetadata(array $metadata): self
    {
        $new = clone $this;
        $new->metadata = array_merge($this->metadata ?? [], $metadata);
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function isFromVisitor(): bool
    {
        return $this->senderType->isVisitor();
    }

    public function isFromAgent(): bool
    {
        return $this->senderType->isAgent();
    }

    public function isSystem(): bool
    {
        return $this->senderType->isSystem();
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function isUnread(): bool
    {
        return $this->readAt === null;
    }

    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    public function hasMetadata(): bool
    {
        return !empty($this->metadata);
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversationId(): int
    {
        return $this->conversationId;
    }

    public function getSenderType(): SenderType
    {
        return $this->senderType;
    }

    public function getSenderId(): ?int
    {
        return $this->senderId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getContentType(): MessageType
    {
        return $this->contentType;
    }

    public function getAttachments(): ?array
    {
        return $this->attachments;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function isInternal(): bool
    {
        return $this->isInternal;
    }

    public function getReadAt(): ?DateTimeImmutable
    {
        return $this->readAt;
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

        if ($this->id === null || $other->getId() === null) {
            return false;
        }

        return $this->id === $other->getId();
    }
}
