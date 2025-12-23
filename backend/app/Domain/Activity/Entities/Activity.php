<?php

declare(strict_types=1);

namespace App\Domain\Activity\Entities;

use App\Domain\Activity\ValueObjects\ActivityAction;
use App\Domain\Activity\ValueObjects\ActivityOutcome;
use App\Domain\Activity\ValueObjects\ActivityType;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;

/**
 * Activity domain entity representing a tracked action or event.
 *
 * Activities can be user-generated (notes, calls, tasks) or system-generated
 * (status changes, field updates). They support scheduling, completion tracking,
 * and can be pinned for visibility.
 */
final class Activity implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private ?int $userId,
        private ActivityType $type,
        private ?ActivityAction $action,
        private ?string $subjectType,
        private ?int $subjectId,
        private ?string $relatedType,
        private ?int $relatedId,
        private ?string $title,
        private ?string $description,
        private array $metadata,
        private ?string $content,
        private bool $isPinned,
        private ?DateTimeImmutable $scheduledAt,
        private ?DateTimeImmutable $completedAt,
        private ?int $durationMinutes,
        private ?ActivityOutcome $outcome,
        private bool $isInternal,
        private bool $isSystem,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    /**
     * Create a new user-generated activity.
     */
    public static function create(
        int $userId,
        ActivityType $type,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $title = null,
        ?string $description = null,
        ?ActivityAction $action = null,
    ): self {
        return new self(
            id: null,
            userId: $userId,
            type: $type,
            action: $action ?? ActivityAction::Created,
            subjectType: $subjectType,
            subjectId: $subjectId,
            relatedType: null,
            relatedId: null,
            title: $title,
            description: $description,
            metadata: [],
            content: null,
            isPinned: false,
            scheduledAt: null,
            completedAt: null,
            durationMinutes: null,
            outcome: null,
            isInternal: false,
            isSystem: false,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * Create a system-generated activity.
     */
    public static function createSystemActivity(
        ActivityType $type,
        ActivityAction $action,
        string $subjectType,
        int $subjectId,
        ?string $title = null,
        array $metadata = [],
    ): self {
        return new self(
            id: null,
            userId: null,
            type: $type,
            action: $action,
            subjectType: $subjectType,
            subjectId: $subjectId,
            relatedType: null,
            relatedId: null,
            title: $title,
            description: null,
            metadata: $metadata,
            content: null,
            isPinned: false,
            scheduledAt: null,
            completedAt: null,
            durationMinutes: null,
            outcome: null,
            isInternal: false,
            isSystem: true,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * Reconstitute an activity from persistence.
     */
    public static function reconstitute(
        int $id,
        ?int $userId,
        ActivityType $type,
        ?ActivityAction $action,
        ?string $subjectType,
        ?int $subjectId,
        ?string $relatedType,
        ?int $relatedId,
        ?string $title,
        ?string $description,
        array $metadata,
        ?string $content,
        bool $isPinned,
        ?DateTimeImmutable $scheduledAt,
        ?DateTimeImmutable $completedAt,
        ?int $durationMinutes,
        ?ActivityOutcome $outcome,
        bool $isInternal,
        bool $isSystem,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            type: $type,
            action: $action,
            subjectType: $subjectType,
            subjectId: $subjectId,
            relatedType: $relatedType,
            relatedId: $relatedId,
            title: $title,
            description: $description,
            metadata: $metadata,
            content: $content,
            isPinned: $isPinned,
            scheduledAt: $scheduledAt,
            completedAt: $completedAt,
            durationMinutes: $durationMinutes,
            outcome: $outcome,
            isInternal: $isInternal,
            isSystem: $isSystem,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    // -------------------------------------------------------------------------
    // Business Logic Methods
    // -------------------------------------------------------------------------

    /**
     * Check if activity is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->scheduledAt === null) {
            return false;
        }

        if ($this->completedAt !== null) {
            return false;
        }

        return $this->scheduledAt < new DateTimeImmutable();
    }

    /**
     * Check if activity is upcoming (scheduled but not completed).
     */
    public function isUpcoming(): bool
    {
        if ($this->scheduledAt === null) {
            return false;
        }

        if ($this->completedAt !== null) {
            return false;
        }

        return $this->scheduledAt > new DateTimeImmutable();
    }

    /**
     * Check if activity is completed.
     */
    public function isCompleted(): bool
    {
        return $this->completedAt !== null;
    }

    /**
     * Mark activity as completed with optional outcome.
     *
     * @return self Returns a new instance with completed state
     */
    public function markCompleted(?ActivityOutcome $outcome = null): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            type: $this->type,
            action: ActivityAction::Completed,
            subjectType: $this->subjectType,
            subjectId: $this->subjectId,
            relatedType: $this->relatedType,
            relatedId: $this->relatedId,
            title: $this->title,
            description: $this->description,
            metadata: $this->metadata,
            content: $this->content,
            isPinned: $this->isPinned,
            scheduledAt: $this->scheduledAt,
            completedAt: new DateTimeImmutable(),
            durationMinutes: $this->durationMinutes,
            outcome: $outcome ?? ActivityOutcome::Completed,
            isInternal: $this->isInternal,
            isSystem: $this->isSystem,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Toggle the pinned status.
     *
     * @return self Returns a new instance with toggled pin state
     */
    public function togglePin(): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            type: $this->type,
            action: $this->action,
            subjectType: $this->subjectType,
            subjectId: $this->subjectId,
            relatedType: $this->relatedType,
            relatedId: $this->relatedId,
            title: $this->title,
            description: $this->description,
            metadata: $this->metadata,
            content: $this->content,
            isPinned: !$this->isPinned,
            scheduledAt: $this->scheduledAt,
            completedAt: $this->completedAt,
            durationMinutes: $this->durationMinutes,
            outcome: $this->outcome,
            isInternal: $this->isInternal,
            isSystem: $this->isSystem,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Schedule the activity for a specific time.
     *
     * @return self Returns a new instance with scheduled time
     */
    public function schedule(DateTimeImmutable $scheduledAt): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            type: $this->type,
            action: ActivityAction::Scheduled,
            subjectType: $this->subjectType,
            subjectId: $this->subjectId,
            relatedType: $this->relatedType,
            relatedId: $this->relatedId,
            title: $this->title,
            description: $this->description,
            metadata: $this->metadata,
            content: $this->content,
            isPinned: $this->isPinned,
            scheduledAt: $scheduledAt,
            completedAt: $this->completedAt,
            durationMinutes: $this->durationMinutes,
            outcome: $this->outcome,
            isInternal: $this->isInternal,
            isSystem: $this->isSystem,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Cancel the activity.
     *
     * @return self Returns a new instance with cancelled state
     */
    public function cancel(): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            type: $this->type,
            action: ActivityAction::Cancelled,
            subjectType: $this->subjectType,
            subjectId: $this->subjectId,
            relatedType: $this->relatedType,
            relatedId: $this->relatedId,
            title: $this->title,
            description: $this->description,
            metadata: $this->metadata,
            content: $this->content,
            isPinned: $this->isPinned,
            scheduledAt: $this->scheduledAt,
            completedAt: new DateTimeImmutable(),
            durationMinutes: $this->durationMinutes,
            outcome: ActivityOutcome::Cancelled,
            isInternal: $this->isInternal,
            isSystem: $this->isSystem,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Update the activity content.
     *
     * @return self Returns a new instance with updated content
     */
    public function updateContent(
        ?string $title = null,
        ?string $description = null,
        ?string $content = null,
    ): self {
        return new self(
            id: $this->id,
            userId: $this->userId,
            type: $this->type,
            action: ActivityAction::Updated,
            subjectType: $this->subjectType,
            subjectId: $this->subjectId,
            relatedType: $this->relatedType,
            relatedId: $this->relatedId,
            title: $title ?? $this->title,
            description: $description ?? $this->description,
            metadata: $this->metadata,
            content: $content ?? $this->content,
            isPinned: $this->isPinned,
            scheduledAt: $this->scheduledAt,
            completedAt: $this->completedAt,
            durationMinutes: $this->durationMinutes,
            outcome: $this->outcome,
            isInternal: $this->isInternal,
            isSystem: $this->isSystem,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Add or update metadata.
     *
     * @return self Returns a new instance with updated metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            type: $this->type,
            action: $this->action,
            subjectType: $this->subjectType,
            subjectId: $this->subjectId,
            relatedType: $this->relatedType,
            relatedId: $this->relatedId,
            title: $this->title,
            description: $this->description,
            metadata: array_merge($this->metadata, $metadata),
            content: $this->content,
            isPinned: $this->isPinned,
            scheduledAt: $this->scheduledAt,
            completedAt: $this->completedAt,
            durationMinutes: $this->durationMinutes,
            outcome: $this->outcome,
            isInternal: $this->isInternal,
            isSystem: $this->isSystem,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Set the related entity.
     *
     * @return self Returns a new instance with related entity
     */
    public function withRelated(string $relatedType, int $relatedId): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            type: $this->type,
            action: $this->action,
            subjectType: $this->subjectType,
            subjectId: $this->subjectId,
            relatedType: $relatedType,
            relatedId: $relatedId,
            title: $this->title,
            description: $this->description,
            metadata: $this->metadata,
            content: $this->content,
            isPinned: $this->isPinned,
            scheduledAt: $this->scheduledAt,
            completedAt: $this->completedAt,
            durationMinutes: $this->durationMinutes,
            outcome: $this->outcome,
            isInternal: $this->isInternal,
            isSystem: $this->isSystem,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Set the duration in minutes.
     *
     * @return self Returns a new instance with duration
     */
    public function withDuration(int $durationMinutes): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            type: $this->type,
            action: $this->action,
            subjectType: $this->subjectType,
            subjectId: $this->subjectId,
            relatedType: $this->relatedType,
            relatedId: $this->relatedId,
            title: $this->title,
            description: $this->description,
            metadata: $this->metadata,
            content: $this->content,
            isPinned: $this->isPinned,
            scheduledAt: $this->scheduledAt,
            completedAt: $this->completedAt,
            durationMinutes: $durationMinutes,
            outcome: $this->outcome,
            isInternal: $this->isInternal,
            isSystem: $this->isSystem,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    // -------------------------------------------------------------------------
    // Presentation Helpers (delegated to Value Objects)
    // -------------------------------------------------------------------------

    /**
     * Get the icon for this activity.
     */
    public function getIcon(): string
    {
        return $this->type->icon();
    }

    /**
     * Get the color for this activity.
     */
    public function getColor(): string
    {
        return $this->type->color();
    }

    /**
     * Get the type label.
     */
    public function getTypeLabel(): string
    {
        return $this->type->label();
    }

    /**
     * Get the action label if action is set.
     */
    public function getActionLabel(): ?string
    {
        return $this->action?->label();
    }

    /**
     * Get the outcome label if outcome is set.
     */
    public function getOutcomeLabel(): ?string
    {
        return $this->outcome?->label();
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getType(): ActivityType
    {
        return $this->type;
    }

    public function getAction(): ?ActivityAction
    {
        return $this->action;
    }

    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): ?int
    {
        return $this->subjectId;
    }

    public function getRelatedType(): ?string
    {
        return $this->relatedType;
    }

    public function getRelatedId(): ?int
    {
        return $this->relatedId;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function isPinned(): bool
    {
        return $this->isPinned;
    }

    public function getScheduledAt(): ?DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function getOutcome(): ?ActivityOutcome
    {
        return $this->outcome;
    }

    public function isInternal(): bool
    {
        return $this->isInternal;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
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
}
