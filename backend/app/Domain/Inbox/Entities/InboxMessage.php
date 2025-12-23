<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Entities;

use App\Domain\Inbox\ValueObjects\MessageDirection;
use App\Domain\Inbox\ValueObjects\MessageType;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * InboxMessage Entity - represents a message within an inbox conversation.
 */
final class InboxMessage implements Entity
{
    /**
     * @param array<string> $toEmails
     * @param array<string> $ccEmails
     * @param array<string> $bccEmails
     * @param array<array{name: string, url: string, size: int, type: string}> $attachments
     */
    private function __construct(
        private ?int $id,
        private int $conversationId,
        private MessageDirection $direction,
        private MessageType $type,
        private ?string $fromEmail,
        private ?string $fromName,
        private array $toEmails,
        private array $ccEmails,
        private array $bccEmails,
        private ?string $subject,
        private ?string $bodyText,
        private ?string $bodyHtml,
        private array $attachments,
        private string $status,
        private ?int $sentBy,
        private ?string $externalMessageId,
        private ?string $inReplyTo,
        private ?string $rawHeaders,
        private ?DateTimeImmutable $sentAt,
        private ?DateTimeImmutable $deliveredAt,
        private ?DateTimeImmutable $readAt,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Create an inbound message.
     */
    public static function createInbound(
        int $conversationId,
        string $fromEmail,
        ?string $fromName,
        ?string $subject,
        ?string $bodyText,
        ?string $bodyHtml = null,
        ?string $externalMessageId = null,
    ): self {
        return new self(
            id: null,
            conversationId: $conversationId,
            direction: MessageDirection::Inbound,
            type: MessageType::Original,
            fromEmail: $fromEmail,
            fromName: $fromName,
            toEmails: [],
            ccEmails: [],
            bccEmails: [],
            subject: $subject,
            bodyText: $bodyText,
            bodyHtml: $bodyHtml,
            attachments: [],
            status: 'received',
            sentBy: null,
            externalMessageId: $externalMessageId,
            inReplyTo: null,
            rawHeaders: null,
            sentAt: new DateTimeImmutable(),
            deliveredAt: null,
            readAt: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    /**
     * Create an outbound reply.
     *
     * @param array<string> $toEmails
     */
    public static function createReply(
        int $conversationId,
        int $sentBy,
        array $toEmails,
        ?string $subject,
        ?string $bodyText,
        ?string $bodyHtml = null,
        ?string $inReplyTo = null,
    ): self {
        if (empty($toEmails)) {
            throw new InvalidArgumentException('Reply must have at least one recipient');
        }

        return new self(
            id: null,
            conversationId: $conversationId,
            direction: MessageDirection::Outbound,
            type: MessageType::Reply,
            fromEmail: null,
            fromName: null,
            toEmails: $toEmails,
            ccEmails: [],
            bccEmails: [],
            subject: $subject,
            bodyText: $bodyText,
            bodyHtml: $bodyHtml,
            attachments: [],
            status: 'pending',
            sentBy: $sentBy,
            externalMessageId: null,
            inReplyTo: $inReplyTo,
            rawHeaders: null,
            sentAt: null,
            deliveredAt: null,
            readAt: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    /**
     * Create an internal note.
     */
    public static function createNote(
        int $conversationId,
        int $sentBy,
        string $bodyText,
    ): self {
        if (empty(trim($bodyText))) {
            throw new InvalidArgumentException('Note content cannot be empty');
        }

        return new self(
            id: null,
            conversationId: $conversationId,
            direction: MessageDirection::Outbound,
            type: MessageType::Note,
            fromEmail: null,
            fromName: null,
            toEmails: [],
            ccEmails: [],
            bccEmails: [],
            subject: null,
            bodyText: $bodyText,
            bodyHtml: null,
            attachments: [],
            status: 'sent',
            sentBy: $sentBy,
            externalMessageId: null,
            inReplyTo: null,
            rawHeaders: null,
            sentAt: new DateTimeImmutable(),
            deliveredAt: null,
            readAt: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     *
     * @param array<string> $toEmails
     * @param array<string> $ccEmails
     * @param array<string> $bccEmails
     * @param array<array{name: string, url: string, size: int, type: string}> $attachments
     */
    public static function reconstitute(
        int $id,
        int $conversationId,
        MessageDirection $direction,
        MessageType $type,
        ?string $fromEmail,
        ?string $fromName,
        array $toEmails,
        array $ccEmails,
        array $bccEmails,
        ?string $subject,
        ?string $bodyText,
        ?string $bodyHtml,
        array $attachments,
        string $status,
        ?int $sentBy,
        ?string $externalMessageId,
        ?string $inReplyTo,
        ?string $rawHeaders,
        ?DateTimeImmutable $sentAt,
        ?DateTimeImmutable $deliveredAt,
        ?DateTimeImmutable $readAt,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            conversationId: $conversationId,
            direction: $direction,
            type: $type,
            fromEmail: $fromEmail,
            fromName: $fromName,
            toEmails: $toEmails,
            ccEmails: $ccEmails,
            bccEmails: $bccEmails,
            subject: $subject,
            bodyText: $bodyText,
            bodyHtml: $bodyHtml,
            attachments: $attachments,
            status: $status,
            sentBy: $sentBy,
            externalMessageId: $externalMessageId,
            inReplyTo: $inReplyTo,
            rawHeaders: $rawHeaders,
            sentAt: $sentAt,
            deliveredAt: $deliveredAt,
            readAt: $readAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // =========================================================================
    // Getters
    // =========================================================================

    public function getId(): ?int { return $this->id; }
    public function getConversationId(): int { return $this->conversationId; }
    public function getDirection(): MessageDirection { return $this->direction; }
    public function getType(): MessageType { return $this->type; }
    public function getFromEmail(): ?string { return $this->fromEmail; }
    public function getFromName(): ?string { return $this->fromName; }
    /** @return array<string> */
    public function getToEmails(): array { return $this->toEmails; }
    /** @return array<string> */
    public function getCcEmails(): array { return $this->ccEmails; }
    /** @return array<string> */
    public function getBccEmails(): array { return $this->bccEmails; }
    public function getSubject(): ?string { return $this->subject; }
    public function getBodyText(): ?string { return $this->bodyText; }
    public function getBodyHtml(): ?string { return $this->bodyHtml; }
    /** @return array<array{name: string, url: string, size: int, type: string}> */
    public function getAttachments(): array { return $this->attachments; }
    public function getStatus(): string { return $this->status; }
    public function getSentBy(): ?int { return $this->sentBy; }
    public function getExternalMessageId(): ?string { return $this->externalMessageId; }
    public function getInReplyTo(): ?string { return $this->inReplyTo; }
    public function getRawHeaders(): ?string { return $this->rawHeaders; }
    public function getSentAt(): ?DateTimeImmutable { return $this->sentAt; }
    public function getDeliveredAt(): ?DateTimeImmutable { return $this->deliveredAt; }
    public function getReadAt(): ?DateTimeImmutable { return $this->readAt; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }

    // =========================================================================
    // State Queries
    // =========================================================================

    public function isInbound(): bool
    {
        return $this->direction === MessageDirection::Inbound;
    }

    public function isOutbound(): bool
    {
        return $this->direction === MessageDirection::Outbound;
    }

    public function isNote(): bool
    {
        return $this->type === MessageType::Note;
    }

    public function isReply(): bool
    {
        return $this->type === MessageType::Reply;
    }

    public function isVisibleToCustomer(): bool
    {
        return $this->type->isVisibleToCustomer();
    }

    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function isDelivered(): bool
    {
        return $this->deliveredAt !== null;
    }

    public function getPlainBody(): string
    {
        if ($this->bodyText) {
            return $this->bodyText;
        }

        if ($this->bodyHtml) {
            return strip_tags($this->bodyHtml);
        }

        return '';
    }

    public function getSnippet(int $length = 100): string
    {
        $text = $this->getPlainBody();
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . '...';
    }

    public function getSenderDisplayName(): string
    {
        return $this->fromName ?? $this->fromEmail ?? 'Unknown';
    }

    // =========================================================================
    // Entity Interface
    // =========================================================================

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

    // =========================================================================
    // State Mutations (Immutable)
    // =========================================================================

    public function markAsRead(): self
    {
        if ($this->readAt !== null) {
            return $this;
        }

        return new self(
            id: $this->id,
            conversationId: $this->conversationId,
            direction: $this->direction,
            type: $this->type,
            fromEmail: $this->fromEmail,
            fromName: $this->fromName,
            toEmails: $this->toEmails,
            ccEmails: $this->ccEmails,
            bccEmails: $this->bccEmails,
            subject: $this->subject,
            bodyText: $this->bodyText,
            bodyHtml: $this->bodyHtml,
            attachments: $this->attachments,
            status: $this->status,
            sentBy: $this->sentBy,
            externalMessageId: $this->externalMessageId,
            inReplyTo: $this->inReplyTo,
            rawHeaders: $this->rawHeaders,
            sentAt: $this->sentAt,
            deliveredAt: $this->deliveredAt,
            readAt: new DateTimeImmutable(),
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function markAsDelivered(): self
    {
        if ($this->deliveredAt !== null) {
            return $this;
        }

        return new self(
            id: $this->id,
            conversationId: $this->conversationId,
            direction: $this->direction,
            type: $this->type,
            fromEmail: $this->fromEmail,
            fromName: $this->fromName,
            toEmails: $this->toEmails,
            ccEmails: $this->ccEmails,
            bccEmails: $this->bccEmails,
            subject: $this->subject,
            bodyText: $this->bodyText,
            bodyHtml: $this->bodyHtml,
            attachments: $this->attachments,
            status: 'delivered',
            sentBy: $this->sentBy,
            externalMessageId: $this->externalMessageId,
            inReplyTo: $this->inReplyTo,
            rawHeaders: $this->rawHeaders,
            sentAt: $this->sentAt,
            deliveredAt: new DateTimeImmutable(),
            readAt: $this->readAt,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function markAsSent(string $externalMessageId = null): self
    {
        return new self(
            id: $this->id,
            conversationId: $this->conversationId,
            direction: $this->direction,
            type: $this->type,
            fromEmail: $this->fromEmail,
            fromName: $this->fromName,
            toEmails: $this->toEmails,
            ccEmails: $this->ccEmails,
            bccEmails: $this->bccEmails,
            subject: $this->subject,
            bodyText: $this->bodyText,
            bodyHtml: $this->bodyHtml,
            attachments: $this->attachments,
            status: 'sent',
            sentBy: $this->sentBy,
            externalMessageId: $externalMessageId ?? $this->externalMessageId,
            inReplyTo: $this->inReplyTo,
            rawHeaders: $this->rawHeaders,
            sentAt: new DateTimeImmutable(),
            deliveredAt: $this->deliveredAt,
            readAt: $this->readAt,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param array<array{name: string, url: string, size: int, type: string}> $attachments
     */
    public function withAttachments(array $attachments): self
    {
        return new self(
            id: $this->id,
            conversationId: $this->conversationId,
            direction: $this->direction,
            type: $this->type,
            fromEmail: $this->fromEmail,
            fromName: $this->fromName,
            toEmails: $this->toEmails,
            ccEmails: $this->ccEmails,
            bccEmails: $this->bccEmails,
            subject: $this->subject,
            bodyText: $this->bodyText,
            bodyHtml: $this->bodyHtml,
            attachments: $attachments,
            status: $this->status,
            sentBy: $this->sentBy,
            externalMessageId: $this->externalMessageId,
            inReplyTo: $this->inReplyTo,
            rawHeaders: $this->rawHeaders,
            sentAt: $this->sentAt,
            deliveredAt: $this->deliveredAt,
            readAt: $this->readAt,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }
}
