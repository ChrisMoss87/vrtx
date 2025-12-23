<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Entities;

use App\Domain\Inbox\ValueObjects\ConversationChannel;
use App\Domain\Inbox\ValueObjects\ConversationPriority;
use App\Domain\Inbox\ValueObjects\ConversationStatus;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * InboxConversation Entity - Aggregate Root for inbox conversations.
 *
 * Represents a customer conversation in a shared inbox, tracking
 * status, assignment, and communication history.
 */
final class InboxConversation implements AggregateRoot
{
    use HasDomainEvents;

    /**
     * @param array<string> $tags
     * @param array<string, mixed> $customFields
     */
    private function __construct(
        private ?int $id,
        private int $inboxId,
        private string $subject,
        private ConversationStatus $status,
        private ConversationPriority $priority,
        private ConversationChannel $channel,
        private ?int $assignedTo,
        private ?int $contactId,
        private ?string $contactEmail,
        private ?string $contactName,
        private ?string $contactPhone,
        private ?string $snippet,
        private ?DateTimeImmutable $firstResponseAt,
        private ?DateTimeImmutable $resolvedAt,
        private ?DateTimeImmutable $lastMessageAt,
        private int $messageCount,
        private ?int $responseTimeSeconds,
        private bool $isSpam,
        private bool $isStarred,
        private array $tags,
        private array $customFields,
        private ?string $externalThreadId,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Create a new inbox conversation.
     */
    public static function create(
        int $inboxId,
        string $subject,
        ConversationChannel $channel,
        ?string $contactEmail = null,
        ?string $contactName = null,
        ?string $contactPhone = null,
        ?int $contactId = null,
        ?string $externalThreadId = null,
    ): self {
        if (empty(trim($subject))) {
            throw new InvalidArgumentException('Subject cannot be empty');
        }

        return new self(
            id: null,
            inboxId: $inboxId,
            subject: trim($subject),
            status: ConversationStatus::Open,
            priority: ConversationPriority::Normal,
            channel: $channel,
            assignedTo: null,
            contactId: $contactId,
            contactEmail: $contactEmail,
            contactName: $contactName,
            contactPhone: $contactPhone,
            snippet: null,
            firstResponseAt: null,
            resolvedAt: null,
            lastMessageAt: new DateTimeImmutable(),
            messageCount: 0,
            responseTimeSeconds: null,
            isSpam: false,
            isStarred: false,
            tags: [],
            customFields: [],
            externalThreadId: $externalThreadId,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     *
     * @param array<string> $tags
     * @param array<string, mixed> $customFields
     */
    public static function reconstitute(
        int $id,
        int $inboxId,
        string $subject,
        ConversationStatus $status,
        ConversationPriority $priority,
        ConversationChannel $channel,
        ?int $assignedTo,
        ?int $contactId,
        ?string $contactEmail,
        ?string $contactName,
        ?string $contactPhone,
        ?string $snippet,
        ?DateTimeImmutable $firstResponseAt,
        ?DateTimeImmutable $resolvedAt,
        ?DateTimeImmutable $lastMessageAt,
        int $messageCount,
        ?int $responseTimeSeconds,
        bool $isSpam,
        bool $isStarred,
        array $tags,
        array $customFields,
        ?string $externalThreadId,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            inboxId: $inboxId,
            subject: $subject,
            status: $status,
            priority: $priority,
            channel: $channel,
            assignedTo: $assignedTo,
            contactId: $contactId,
            contactEmail: $contactEmail,
            contactName: $contactName,
            contactPhone: $contactPhone,
            snippet: $snippet,
            firstResponseAt: $firstResponseAt,
            resolvedAt: $resolvedAt,
            lastMessageAt: $lastMessageAt,
            messageCount: $messageCount,
            responseTimeSeconds: $responseTimeSeconds,
            isSpam: $isSpam,
            isStarred: $isStarred,
            tags: $tags,
            customFields: $customFields,
            externalThreadId: $externalThreadId,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // =========================================================================
    // Getters
    // =========================================================================

    public function getId(): ?int { return $this->id; }
    public function getInboxId(): int { return $this->inboxId; }
    public function getSubject(): string { return $this->subject; }
    public function getStatus(): ConversationStatus { return $this->status; }
    public function getPriority(): ConversationPriority { return $this->priority; }
    public function getChannel(): ConversationChannel { return $this->channel; }
    public function getAssignedTo(): ?int { return $this->assignedTo; }
    public function getContactId(): ?int { return $this->contactId; }
    public function getContactEmail(): ?string { return $this->contactEmail; }
    public function getContactName(): ?string { return $this->contactName; }
    public function getContactPhone(): ?string { return $this->contactPhone; }
    public function getSnippet(): ?string { return $this->snippet; }
    public function getFirstResponseAt(): ?DateTimeImmutable { return $this->firstResponseAt; }
    public function getResolvedAt(): ?DateTimeImmutable { return $this->resolvedAt; }
    public function getLastMessageAt(): ?DateTimeImmutable { return $this->lastMessageAt; }
    public function getMessageCount(): int { return $this->messageCount; }
    public function getResponseTimeSeconds(): ?int { return $this->responseTimeSeconds; }
    public function isSpam(): bool { return $this->isSpam; }
    public function isStarred(): bool { return $this->isStarred; }
    /** @return array<string> */
    public function getTags(): array { return $this->tags; }
    /** @return array<string, mixed> */
    public function getCustomFields(): array { return $this->customFields; }
    public function getExternalThreadId(): ?string { return $this->externalThreadId; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }

    // =========================================================================
    // State Queries
    // =========================================================================

    public function isOpen(): bool
    {
        return $this->status === ConversationStatus::Open;
    }

    public function isResolved(): bool
    {
        return $this->status->isComplete();
    }

    public function isAssigned(): bool
    {
        return $this->assignedTo !== null;
    }

    public function isUnassigned(): bool
    {
        return $this->assignedTo === null;
    }

    public function hasContact(): bool
    {
        return $this->contactId !== null;
    }

    public function hasFirstResponse(): bool
    {
        return $this->firstResponseAt !== null;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    public function getContactDisplayName(): string
    {
        return $this->contactName ?? $this->contactEmail ?? 'Unknown';
    }

    public function getResponseTimeFormatted(): ?string
    {
        if ($this->responseTimeSeconds === null) {
            return null;
        }

        $seconds = $this->responseTimeSeconds;
        if ($seconds < 60) {
            return "{$seconds} seconds";
        }

        $minutes = floor($seconds / 60);
        if ($minutes < 60) {
            return "{$minutes} minute" . ($minutes !== 1 ? 's' : '');
        }

        $hours = floor($minutes / 60);
        return "{$hours} hour" . ($hours !== 1 ? 's' : '');
    }

    // =========================================================================
    // State Mutations (Immutable)
    // =========================================================================

    public function assignTo(?int $userId): self
    {
        return new self(
            id: $this->id,
            inboxId: $this->inboxId,
            subject: $this->subject,
            status: $this->status,
            priority: $this->priority,
            channel: $this->channel,
            assignedTo: $userId,
            contactId: $this->contactId,
            contactEmail: $this->contactEmail,
            contactName: $this->contactName,
            contactPhone: $this->contactPhone,
            snippet: $this->snippet,
            firstResponseAt: $this->firstResponseAt,
            resolvedAt: $this->resolvedAt,
            lastMessageAt: $this->lastMessageAt,
            messageCount: $this->messageCount,
            responseTimeSeconds: $this->responseTimeSeconds,
            isSpam: $this->isSpam,
            isStarred: $this->isStarred,
            tags: $this->tags,
            customFields: $this->customFields,
            externalThreadId: $this->externalThreadId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function resolve(): self
    {
        if (!$this->status->canResolve()) {
            return $this;
        }

        return new self(
            id: $this->id,
            inboxId: $this->inboxId,
            subject: $this->subject,
            status: ConversationStatus::Resolved,
            priority: $this->priority,
            channel: $this->channel,
            assignedTo: $this->assignedTo,
            contactId: $this->contactId,
            contactEmail: $this->contactEmail,
            contactName: $this->contactName,
            contactPhone: $this->contactPhone,
            snippet: $this->snippet,
            firstResponseAt: $this->firstResponseAt,
            resolvedAt: new DateTimeImmutable(),
            lastMessageAt: $this->lastMessageAt,
            messageCount: $this->messageCount,
            responseTimeSeconds: $this->responseTimeSeconds,
            isSpam: $this->isSpam,
            isStarred: $this->isStarred,
            tags: $this->tags,
            customFields: $this->customFields,
            externalThreadId: $this->externalThreadId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function reopen(): self
    {
        if (!$this->status->canReopen()) {
            return $this;
        }

        return new self(
            id: $this->id,
            inboxId: $this->inboxId,
            subject: $this->subject,
            status: ConversationStatus::Open,
            priority: $this->priority,
            channel: $this->channel,
            assignedTo: $this->assignedTo,
            contactId: $this->contactId,
            contactEmail: $this->contactEmail,
            contactName: $this->contactName,
            contactPhone: $this->contactPhone,
            snippet: $this->snippet,
            firstResponseAt: $this->firstResponseAt,
            resolvedAt: null,
            lastMessageAt: $this->lastMessageAt,
            messageCount: $this->messageCount,
            responseTimeSeconds: $this->responseTimeSeconds,
            isSpam: $this->isSpam,
            isStarred: $this->isStarred,
            tags: $this->tags,
            customFields: $this->customFields,
            externalThreadId: $this->externalThreadId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function close(): self
    {
        return new self(
            id: $this->id,
            inboxId: $this->inboxId,
            subject: $this->subject,
            status: ConversationStatus::Closed,
            priority: $this->priority,
            channel: $this->channel,
            assignedTo: $this->assignedTo,
            contactId: $this->contactId,
            contactEmail: $this->contactEmail,
            contactName: $this->contactName,
            contactPhone: $this->contactPhone,
            snippet: $this->snippet,
            firstResponseAt: $this->firstResponseAt,
            resolvedAt: $this->resolvedAt ?? new DateTimeImmutable(),
            lastMessageAt: $this->lastMessageAt,
            messageCount: $this->messageCount,
            responseTimeSeconds: $this->responseTimeSeconds,
            isSpam: $this->isSpam,
            isStarred: $this->isStarred,
            tags: $this->tags,
            customFields: $this->customFields,
            externalThreadId: $this->externalThreadId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function markAsSpam(): self
    {
        return new self(
            id: $this->id,
            inboxId: $this->inboxId,
            subject: $this->subject,
            status: $this->status,
            priority: $this->priority,
            channel: $this->channel,
            assignedTo: $this->assignedTo,
            contactId: $this->contactId,
            contactEmail: $this->contactEmail,
            contactName: $this->contactName,
            contactPhone: $this->contactPhone,
            snippet: $this->snippet,
            firstResponseAt: $this->firstResponseAt,
            resolvedAt: $this->resolvedAt,
            lastMessageAt: $this->lastMessageAt,
            messageCount: $this->messageCount,
            responseTimeSeconds: $this->responseTimeSeconds,
            isSpam: true,
            isStarred: $this->isStarred,
            tags: $this->tags,
            customFields: $this->customFields,
            externalThreadId: $this->externalThreadId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function toggleStar(): self
    {
        return new self(
            id: $this->id,
            inboxId: $this->inboxId,
            subject: $this->subject,
            status: $this->status,
            priority: $this->priority,
            channel: $this->channel,
            assignedTo: $this->assignedTo,
            contactId: $this->contactId,
            contactEmail: $this->contactEmail,
            contactName: $this->contactName,
            contactPhone: $this->contactPhone,
            snippet: $this->snippet,
            firstResponseAt: $this->firstResponseAt,
            resolvedAt: $this->resolvedAt,
            lastMessageAt: $this->lastMessageAt,
            messageCount: $this->messageCount,
            responseTimeSeconds: $this->responseTimeSeconds,
            isSpam: $this->isSpam,
            isStarred: !$this->isStarred,
            tags: $this->tags,
            customFields: $this->customFields,
            externalThreadId: $this->externalThreadId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function setPriority(ConversationPriority $priority): self
    {
        return new self(
            id: $this->id,
            inboxId: $this->inboxId,
            subject: $this->subject,
            status: $this->status,
            priority: $priority,
            channel: $this->channel,
            assignedTo: $this->assignedTo,
            contactId: $this->contactId,
            contactEmail: $this->contactEmail,
            contactName: $this->contactName,
            contactPhone: $this->contactPhone,
            snippet: $this->snippet,
            firstResponseAt: $this->firstResponseAt,
            resolvedAt: $this->resolvedAt,
            lastMessageAt: $this->lastMessageAt,
            messageCount: $this->messageCount,
            responseTimeSeconds: $this->responseTimeSeconds,
            isSpam: $this->isSpam,
            isStarred: $this->isStarred,
            tags: $this->tags,
            customFields: $this->customFields,
            externalThreadId: $this->externalThreadId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function addTag(string $tag): self
    {
        if ($this->hasTag($tag)) {
            return $this;
        }

        $tags = $this->tags;
        $tags[] = $tag;

        return new self(
            id: $this->id,
            inboxId: $this->inboxId,
            subject: $this->subject,
            status: $this->status,
            priority: $this->priority,
            channel: $this->channel,
            assignedTo: $this->assignedTo,
            contactId: $this->contactId,
            contactEmail: $this->contactEmail,
            contactName: $this->contactName,
            contactPhone: $this->contactPhone,
            snippet: $this->snippet,
            firstResponseAt: $this->firstResponseAt,
            resolvedAt: $this->resolvedAt,
            lastMessageAt: $this->lastMessageAt,
            messageCount: $this->messageCount,
            responseTimeSeconds: $this->responseTimeSeconds,
            isSpam: $this->isSpam,
            isStarred: $this->isStarred,
            tags: $tags,
            customFields: $this->customFields,
            externalThreadId: $this->externalThreadId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function removeTag(string $tag): self
    {
        if (!$this->hasTag($tag)) {
            return $this;
        }

        $tags = array_values(array_diff($this->tags, [$tag]));

        return new self(
            id: $this->id,
            inboxId: $this->inboxId,
            subject: $this->subject,
            status: $this->status,
            priority: $this->priority,
            channel: $this->channel,
            assignedTo: $this->assignedTo,
            contactId: $this->contactId,
            contactEmail: $this->contactEmail,
            contactName: $this->contactName,
            contactPhone: $this->contactPhone,
            snippet: $this->snippet,
            firstResponseAt: $this->firstResponseAt,
            resolvedAt: $this->resolvedAt,
            lastMessageAt: $this->lastMessageAt,
            messageCount: $this->messageCount,
            responseTimeSeconds: $this->responseTimeSeconds,
            isSpam: $this->isSpam,
            isStarred: $this->isStarred,
            tags: $tags,
            customFields: $this->customFields,
            externalThreadId: $this->externalThreadId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function recordFirstResponse(): self
    {
        if ($this->firstResponseAt !== null) {
            return $this;
        }

        $now = new DateTimeImmutable();
        $responseTimeSeconds = $this->createdAt
            ? $now->getTimestamp() - $this->createdAt->getTimestamp()
            : null;

        return new self(
            id: $this->id,
            inboxId: $this->inboxId,
            subject: $this->subject,
            status: $this->status,
            priority: $this->priority,
            channel: $this->channel,
            assignedTo: $this->assignedTo,
            contactId: $this->contactId,
            contactEmail: $this->contactEmail,
            contactName: $this->contactName,
            contactPhone: $this->contactPhone,
            snippet: $this->snippet,
            firstResponseAt: $now,
            resolvedAt: $this->resolvedAt,
            lastMessageAt: $this->lastMessageAt,
            messageCount: $this->messageCount,
            responseTimeSeconds: $responseTimeSeconds,
            isSpam: $this->isSpam,
            isStarred: $this->isStarred,
            tags: $this->tags,
            customFields: $this->customFields,
            externalThreadId: $this->externalThreadId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function incrementMessageCount(string $snippet = null): self
    {
        return new self(
            id: $this->id,
            inboxId: $this->inboxId,
            subject: $this->subject,
            status: $this->status,
            priority: $this->priority,
            channel: $this->channel,
            assignedTo: $this->assignedTo,
            contactId: $this->contactId,
            contactEmail: $this->contactEmail,
            contactName: $this->contactName,
            contactPhone: $this->contactPhone,
            snippet: $snippet ?? $this->snippet,
            firstResponseAt: $this->firstResponseAt,
            resolvedAt: $this->resolvedAt,
            lastMessageAt: new DateTimeImmutable(),
            messageCount: $this->messageCount + 1,
            responseTimeSeconds: $this->responseTimeSeconds,
            isSpam: $this->isSpam,
            isStarred: $this->isStarred,
            tags: $this->tags,
            customFields: $this->customFields,
            externalThreadId: $this->externalThreadId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }
}
