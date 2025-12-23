<?php

declare(strict_types=1);

namespace App\Domain\Chat\Entities;

use App\Domain\Chat\ValueObjects\ConversationPriority;
use App\Domain\Chat\ValueObjects\ConversationStatus;
use App\Domain\Chat\ValueObjects\Rating;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;
use InvalidArgumentException;

final class ChatConversation implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private int $widgetId,
        private int $visitorId,
        private ?int $contactId,
        private ?int $assignedTo,
        private ConversationStatus $status,
        private ConversationPriority $priority,
        private ?string $department,
        private ?string $subject,
        private array $tags,
        private int $messageCount,
        private int $visitorMessageCount,
        private int $agentMessageCount,
        private ?Rating $rating,
        private ?DateTimeImmutable $firstResponseAt,
        private ?DateTimeImmutable $resolvedAt,
        private ?DateTimeImmutable $lastMessageAt,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $widgetId,
        int $visitorId,
        ?int $contactId = null,
        ?string $subject = null,
        ?string $department = null,
        ConversationPriority $priority = ConversationPriority::NORMAL,
    ): self {
        if ($widgetId <= 0) {
            throw new InvalidArgumentException('Widget ID must be positive');
        }

        if ($visitorId <= 0) {
            throw new InvalidArgumentException('Visitor ID must be positive');
        }

        return new self(
            id: null,
            widgetId: $widgetId,
            visitorId: $visitorId,
            contactId: $contactId,
            assignedTo: null,
            status: ConversationStatus::OPEN,
            priority: $priority,
            department: $department,
            subject: $subject,
            tags: [],
            messageCount: 0,
            visitorMessageCount: 0,
            agentMessageCount: 0,
            rating: null,
            firstResponseAt: null,
            resolvedAt: null,
            lastMessageAt: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $widgetId,
        int $visitorId,
        ?int $contactId,
        ?int $assignedTo,
        ConversationStatus $status,
        ConversationPriority $priority,
        ?string $department,
        ?string $subject,
        array $tags,
        int $messageCount,
        int $visitorMessageCount,
        int $agentMessageCount,
        ?Rating $rating,
        ?DateTimeImmutable $firstResponseAt,
        ?DateTimeImmutable $resolvedAt,
        ?DateTimeImmutable $lastMessageAt,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            widgetId: $widgetId,
            visitorId: $visitorId,
            contactId: $contactId,
            assignedTo: $assignedTo,
            status: $status,
            priority: $priority,
            department: $department,
            subject: $subject,
            tags: $tags,
            messageCount: $messageCount,
            visitorMessageCount: $visitorMessageCount,
            agentMessageCount: $agentMessageCount,
            rating: $rating,
            firstResponseAt: $firstResponseAt,
            resolvedAt: $resolvedAt,
            lastMessageAt: $lastMessageAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function assign(int $userId): self
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }

        if ($this->assignedTo === $userId) {
            return $this;
        }

        $new = clone $this;
        $new->assignedTo = $userId;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function unassign(): self
    {
        if ($this->assignedTo === null) {
            return $this;
        }

        $new = clone $this;
        $new->assignedTo = null;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function close(): self
    {
        if (!$this->status->canBeClosed()) {
            throw new InvalidArgumentException('Conversation cannot be closed in current status');
        }

        $new = clone $this;
        $new->status = ConversationStatus::CLOSED;
        $new->resolvedAt = new DateTimeImmutable();
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function reopen(): self
    {
        if (!$this->status->canBeReopened()) {
            throw new InvalidArgumentException('Only closed conversations can be reopened');
        }

        $new = clone $this;
        $new->status = ConversationStatus::OPEN;
        $new->resolvedAt = null;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function setPending(): self
    {
        if ($this->status->isClosed()) {
            throw new InvalidArgumentException('Cannot set closed conversation to pending');
        }

        $new = clone $this;
        $new->status = ConversationStatus::PENDING;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function changePriority(ConversationPriority $priority): self
    {
        if ($this->priority === $priority) {
            return $this;
        }

        $new = clone $this;
        $new->priority = $priority;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function updateSubject(string $subject): self
    {
        if (empty(trim($subject))) {
            throw new InvalidArgumentException('Subject cannot be empty');
        }

        if ($this->subject === $subject) {
            return $this;
        }

        $new = clone $this;
        $new->subject = $subject;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function addTag(string $tag): self
    {
        $tag = trim($tag);
        if (empty($tag)) {
            throw new InvalidArgumentException('Tag cannot be empty');
        }

        if (in_array($tag, $this->tags, true)) {
            return $this;
        }

        $new = clone $this;
        $new->tags = [...$this->tags, $tag];
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function removeTag(string $tag): self
    {
        $key = array_search($tag, $this->tags, true);
        if ($key === false) {
            return $this;
        }

        $new = clone $this;
        $new->tags = array_values(array_filter($this->tags, fn($t) => $t !== $tag));
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function recordVisitorMessage(): self
    {
        $new = clone $this;
        $new->messageCount++;
        $new->visitorMessageCount++;
        $new->lastMessageAt = new DateTimeImmutable();
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function recordAgentMessage(): self
    {
        $new = clone $this;
        $new->messageCount++;
        $new->agentMessageCount++;
        $new->lastMessageAt = new DateTimeImmutable();

        if ($this->firstResponseAt === null) {
            $new->firstResponseAt = new DateTimeImmutable();
        }

        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function recordSystemMessage(): self
    {
        $new = clone $this;
        $new->messageCount++;
        $new->lastMessageAt = new DateTimeImmutable();
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function rate(Rating $rating): self
    {
        if (!$this->status->isClosed()) {
            throw new InvalidArgumentException('Can only rate closed conversations');
        }

        $new = clone $this;
        $new->rating = $rating;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function linkContact(int $contactId): self
    {
        if ($contactId <= 0) {
            throw new InvalidArgumentException('Contact ID must be positive');
        }

        if ($this->contactId === $contactId) {
            return $this;
        }

        $new = clone $this;
        $new->contactId = $contactId;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function assignToDepartment(string $department): self
    {
        if (empty(trim($department))) {
            throw new InvalidArgumentException('Department cannot be empty');
        }

        if ($this->department === $department) {
            return $this;
        }

        $new = clone $this;
        $new->department = $department;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function getFirstResponseTimeMinutes(): ?int
    {
        if ($this->firstResponseAt === null || $this->createdAt === null) {
            return null;
        }

        return (int) (($this->firstResponseAt->getTimestamp() - $this->createdAt->getTimestamp()) / 60);
    }

    public function getResolutionTimeMinutes(): ?int
    {
        if ($this->resolvedAt === null || $this->createdAt === null) {
            return null;
        }

        return (int) (($this->resolvedAt->getTimestamp() - $this->createdAt->getTimestamp()) / 60);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    public function isAssigned(): bool
    {
        return $this->assignedTo !== null;
    }

    public function hasRating(): bool
    {
        return $this->rating !== null;
    }

    public function hasBeenResponded(): bool
    {
        return $this->firstResponseAt !== null;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWidgetId(): int
    {
        return $this->widgetId;
    }

    public function getVisitorId(): int
    {
        return $this->visitorId;
    }

    public function getContactId(): ?int
    {
        return $this->contactId;
    }

    public function getAssignedTo(): ?int
    {
        return $this->assignedTo;
    }

    public function getStatus(): ConversationStatus
    {
        return $this->status;
    }

    public function getPriority(): ConversationPriority
    {
        return $this->priority;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getMessageCount(): int
    {
        return $this->messageCount;
    }

    public function getVisitorMessageCount(): int
    {
        return $this->visitorMessageCount;
    }

    public function getAgentMessageCount(): int
    {
        return $this->agentMessageCount;
    }

    public function getRating(): ?Rating
    {
        return $this->rating;
    }

    public function getFirstResponseAt(): ?DateTimeImmutable
    {
        return $this->firstResponseAt;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function getLastMessageAt(): ?DateTimeImmutable
    {
        return $this->lastMessageAt;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
