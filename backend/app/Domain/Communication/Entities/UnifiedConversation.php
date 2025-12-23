<?php

declare(strict_types=1);

namespace App\Domain\Communication\Entities;

use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Communication\ValueObjects\ConversationStatus;
use App\Domain\Communication\ValueObjects\MessageParticipant;
use App\Domain\Communication\ValueObjects\RecordContext;

class UnifiedConversation
{
    private function __construct(
        private ?int $id,
        private ChannelType $channel,
        private ConversationStatus $status,
        private ?string $subject,
        private MessageParticipant $contact,
        private ?int $assignedTo,
        private ?RecordContext $linkedRecord,
        private ?string $sourceConversationId,
        private ?string $externalThreadId,
        private array $tags,
        private int $messageCount,
        private ?\DateTimeImmutable $lastMessageAt,
        private ?\DateTimeImmutable $firstResponseAt,
        private ?int $responseTimeSeconds,
        private array $metadata,
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        ChannelType $channel,
        MessageParticipant $contact,
        ?string $subject = null,
        ?string $sourceConversationId = null,
    ): self {
        return new self(
            id: null,
            channel: $channel,
            status: ConversationStatus::OPEN,
            subject: $subject,
            contact: $contact,
            assignedTo: null,
            linkedRecord: null,
            sourceConversationId: $sourceConversationId,
            externalThreadId: null,
            tags: [],
            messageCount: 0,
            lastMessageAt: null,
            firstResponseAt: null,
            responseTimeSeconds: null,
            metadata: [],
            createdAt: new \DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        ChannelType $channel,
        ConversationStatus $status,
        ?string $subject,
        MessageParticipant $contact,
        ?int $assignedTo,
        ?RecordContext $linkedRecord,
        ?string $sourceConversationId,
        ?string $externalThreadId,
        array $tags,
        int $messageCount,
        ?\DateTimeImmutable $lastMessageAt,
        ?\DateTimeImmutable $firstResponseAt,
        ?int $responseTimeSeconds,
        array $metadata,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            channel: $channel,
            status: $status,
            subject: $subject,
            contact: $contact,
            assignedTo: $assignedTo,
            linkedRecord: $linkedRecord,
            sourceConversationId: $sourceConversationId,
            externalThreadId: $externalThreadId,
            tags: $tags,
            messageCount: $messageCount,
            lastMessageAt: $lastMessageAt,
            firstResponseAt: $firstResponseAt,
            responseTimeSeconds: $responseTimeSeconds,
            metadata: $metadata,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getChannel(): ChannelType { return $this->channel; }
    public function getStatus(): ConversationStatus { return $this->status; }
    public function getSubject(): ?string { return $this->subject; }
    public function getContact(): MessageParticipant { return $this->contact; }
    public function getAssignedTo(): ?int { return $this->assignedTo; }
    public function getLinkedRecord(): ?RecordContext { return $this->linkedRecord; }
    public function getSourceConversationId(): ?string { return $this->sourceConversationId; }
    public function getExternalThreadId(): ?string { return $this->externalThreadId; }
    public function getTags(): array { return $this->tags; }
    public function getMessageCount(): int { return $this->messageCount; }
    public function getLastMessageAt(): ?\DateTimeImmutable { return $this->lastMessageAt; }
    public function getFirstResponseAt(): ?\DateTimeImmutable { return $this->firstResponseAt; }
    public function getResponseTimeSeconds(): ?int { return $this->responseTimeSeconds; }
    public function getMetadata(): array { return $this->metadata; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    // Domain methods
    public function assign(int $userId): void
    {
        $this->assignedTo = $userId;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function unassign(): void
    {
        $this->assignedTo = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function linkToRecord(RecordContext $record): void
    {
        $this->linkedRecord = $record;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function unlinkRecord(): void
    {
        $this->linkedRecord = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateStatus(ConversationStatus $status): void
    {
        if (!$this->status->canTransitionTo($status)) {
            throw new \InvalidArgumentException(
                "Cannot transition from {$this->status->value} to {$status->value}"
            );
        }

        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function resolve(): void
    {
        $this->updateStatus(ConversationStatus::RESOLVED);
    }

    public function close(): void
    {
        $this->updateStatus(ConversationStatus::CLOSED);
    }

    public function reopen(): void
    {
        $this->updateStatus(ConversationStatus::OPEN);
    }

    public function addTag(string $tag): void
    {
        if (!in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function removeTag(string $tag): void
    {
        $key = array_search($tag, $this->tags, true);
        if ($key !== false) {
            unset($this->tags[$key]);
            $this->tags = array_values($this->tags);
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function recordMessage(\DateTimeImmutable $messageTime): void
    {
        $this->messageCount++;
        $this->lastMessageAt = $messageTime;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function recordFirstResponse(\DateTimeImmutable $responseTime): void
    {
        if ($this->firstResponseAt === null) {
            $this->firstResponseAt = $responseTime;
            $this->responseTimeSeconds = $responseTime->getTimestamp() - $this->createdAt->getTimestamp();
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isAssigned(): bool
    {
        return $this->assignedTo !== null;
    }

    public function isLinkedToRecord(): bool
    {
        return $this->linkedRecord !== null;
    }
}
