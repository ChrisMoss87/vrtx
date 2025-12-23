<?php

declare(strict_types=1);

namespace App\Domain\Communication\Entities;

use App\Domain\Communication\Contracts\UnifiedMessageInterface;
use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Communication\ValueObjects\MessageDirection;
use App\Domain\Communication\ValueObjects\MessageParticipant;

class UnifiedMessage implements UnifiedMessageInterface
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';

    private function __construct(
        private ?int $id,
        private int $conversationId,
        private ChannelType $channel,
        private MessageDirection $direction,
        private ?string $content,
        private ?string $htmlContent,
        private MessageParticipant $sender,
        private array $recipients,
        private array $attachments,
        private ?string $sourceMessageId,
        private ?string $externalMessageId,
        private string $status,
        private ?\DateTimeImmutable $sentAt,
        private ?\DateTimeImmutable $deliveredAt,
        private ?\DateTimeImmutable $readAt,
        private array $metadata,
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        int $conversationId,
        ChannelType $channel,
        MessageDirection $direction,
        MessageParticipant $sender,
        array $recipients,
        ?string $content = null,
        ?string $htmlContent = null,
        ?string $sourceMessageId = null,
    ): self {
        return new self(
            id: null,
            conversationId: $conversationId,
            channel: $channel,
            direction: $direction,
            content: $content,
            htmlContent: $htmlContent,
            sender: $sender,
            recipients: $recipients,
            attachments: [],
            sourceMessageId: $sourceMessageId,
            externalMessageId: null,
            status: self::STATUS_PENDING,
            sentAt: null,
            deliveredAt: null,
            readAt: null,
            metadata: [],
            createdAt: new \DateTimeImmutable(),
        );
    }

    public static function reconstitute(
        int $id,
        int $conversationId,
        ChannelType $channel,
        MessageDirection $direction,
        ?string $content,
        ?string $htmlContent,
        MessageParticipant $sender,
        array $recipients,
        array $attachments,
        ?string $sourceMessageId,
        ?string $externalMessageId,
        string $status,
        ?\DateTimeImmutable $sentAt,
        ?\DateTimeImmutable $deliveredAt,
        ?\DateTimeImmutable $readAt,
        array $metadata,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            conversationId: $conversationId,
            channel: $channel,
            direction: $direction,
            content: $content,
            htmlContent: $htmlContent,
            sender: $sender,
            recipients: $recipients,
            attachments: $attachments,
            sourceMessageId: $sourceMessageId,
            externalMessageId: $externalMessageId,
            status: $status,
            sentAt: $sentAt,
            deliveredAt: $deliveredAt,
            readAt: $readAt,
            metadata: $metadata,
            createdAt: $createdAt,
        );
    }

    // Interface implementation
    public function getId(): ?int { return $this->id; }
    public function getConversationId(): int { return $this->conversationId; }
    public function getChannelType(): ChannelType { return $this->channel; }
    public function getDirection(): MessageDirection { return $this->direction; }
    public function getContent(): ?string { return $this->content; }
    public function getHtmlContent(): ?string { return $this->htmlContent; }
    public function getSender(): MessageParticipant { return $this->sender; }
    public function getRecipients(): array { return $this->recipients; }
    public function getAttachments(): array { return $this->attachments; }
    public function getSourceMessageId(): ?string { return $this->sourceMessageId; }
    public function getExternalMessageId(): ?string { return $this->externalMessageId; }
    public function getStatus(): string { return $this->status; }
    public function getMetadata(): array { return $this->metadata; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function getDeliveredAt(): ?\DateTimeImmutable { return $this->deliveredAt; }
    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; }

    // Domain methods
    public function markAsSending(): void
    {
        $this->status = self::STATUS_SENDING;
    }

    public function markAsSent(string $externalMessageId): void
    {
        $this->externalMessageId = $externalMessageId;
        $this->status = self::STATUS_SENT;
        $this->sentAt = new \DateTimeImmutable();
    }

    public function markAsDelivered(): void
    {
        $this->status = self::STATUS_DELIVERED;
        $this->deliveredAt = new \DateTimeImmutable();
    }

    public function markAsRead(): void
    {
        $this->status = self::STATUS_READ;
        $this->readAt = new \DateTimeImmutable();
    }

    public function markAsFailed(): void
    {
        $this->status = self::STATUS_FAILED;
    }

    public function addAttachment(array $attachment): void
    {
        $this->attachments[] = $attachment;
    }

    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function isInbound(): bool
    {
        return $this->direction->isInbound();
    }

    public function isOutbound(): bool
    {
        return $this->direction->isOutbound();
    }

    public function isSent(): bool
    {
        return in_array($this->status, [
            self::STATUS_SENT,
            self::STATUS_DELIVERED,
            self::STATUS_READ,
        ]);
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getDisplayContent(): string
    {
        return $this->content ?? strip_tags($this->htmlContent ?? '');
    }

    public function getSnippet(int $maxLength = 100): string
    {
        $content = $this->getDisplayContent();
        if (strlen($content) <= $maxLength) {
            return $content;
        }
        return substr($content, 0, $maxLength - 3) . '...';
    }
}
