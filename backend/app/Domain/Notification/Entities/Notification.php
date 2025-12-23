<?php

declare(strict_types=1);

namespace App\Domain\Notification\Entities;

use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class Notification implements Entity
{
    // Categories
    public const CATEGORY_APPROVALS = 'approvals';
    public const CATEGORY_ASSIGNMENTS = 'assignments';
    public const CATEGORY_MENTIONS = 'mentions';
    public const CATEGORY_UPDATES = 'updates';
    public const CATEGORY_REMINDERS = 'reminders';
    public const CATEGORY_DEALS = 'deals';
    public const CATEGORY_TASKS = 'tasks';
    public const CATEGORY_SYSTEM = 'system';

    // Notification types
    public const TYPE_APPROVAL_PENDING = 'approval.pending';
    public const TYPE_APPROVAL_APPROVED = 'approval.approved';
    public const TYPE_APPROVAL_REJECTED = 'approval.rejected';
    public const TYPE_APPROVAL_ESCALATED = 'approval.escalated';
    public const TYPE_APPROVAL_REMINDER = 'approval.reminder';
    public const TYPE_ASSIGNMENT_NEW = 'assignment.new';
    public const TYPE_ASSIGNMENT_CHANGED = 'assignment.changed';
    public const TYPE_MENTION_COMMENT = 'mention.comment';
    public const TYPE_MENTION_NOTE = 'mention.note';
    public const TYPE_RECORD_UPDATED = 'record.updated';
    public const TYPE_RECORD_DELETED = 'record.deleted';
    public const TYPE_REMINDER_TASK = 'reminder.task';
    public const TYPE_REMINDER_ACTIVITY = 'reminder.activity';
    public const TYPE_REMINDER_FOLLOWUP = 'reminder.followup';
    public const TYPE_DEAL_WON = 'deal.won';
    public const TYPE_DEAL_LOST = 'deal.lost';
    public const TYPE_DEAL_STAGE_CHANGED = 'deal.stage_changed';
    public const TYPE_TASK_ASSIGNED = 'task.assigned';
    public const TYPE_TASK_COMPLETED = 'task.completed';
    public const TYPE_TASK_OVERDUE = 'task.overdue';
    public const TYPE_SYSTEM_ANNOUNCEMENT = 'system.announcement';
    public const TYPE_SYSTEM_MAINTENANCE = 'system.maintenance';

    private function __construct(
        private ?int $id,
        private int $userId,
        private string $type,
        private string $category,
        private string $title,
        private ?string $body,
        private ?string $icon,
        private ?string $iconColor,
        private ?string $actionUrl,
        private ?string $actionLabel,
        private ?string $notifiableType,
        private ?int $notifiableId,
        private array $data,
        private ?DateTimeImmutable $readAt,
        private ?DateTimeImmutable $archivedAt,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?string $notifiableType = null,
        ?int $notifiableId = null,
        array $data = [],
    ): self {
        $category = self::getCategoryFromType($type);
        $iconDefaults = self::getIconDefaults($type);

        return new self(
            id: null,
            userId: $userId,
            type: $type,
            category: $category,
            title: $title,
            body: $body,
            icon: $iconDefaults['icon'],
            iconColor: $iconDefaults['color'],
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
            notifiableType: $notifiableType,
            notifiableId: $notifiableId,
            data: $data,
            readAt: null,
            archivedAt: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $userId,
        string $type,
        string $category,
        string $title,
        ?string $body,
        ?string $icon,
        ?string $iconColor,
        ?string $actionUrl,
        ?string $actionLabel,
        ?string $notifiableType,
        ?int $notifiableId,
        array $data,
        ?DateTimeImmutable $readAt,
        ?DateTimeImmutable $archivedAt,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            type: $type,
            category: $category,
            title: $title,
            body: $body,
            icon: $icon,
            iconColor: $iconColor,
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
            notifiableType: $notifiableType,
            notifiableId: $notifiableId,
            data: $data,
            readAt: $readAt,
            archivedAt: $archivedAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getUserId(): int { return $this->userId; }
    public function getType(): string { return $this->type; }
    public function getCategory(): string { return $this->category; }
    public function getTitle(): string { return $this->title; }
    public function getBody(): ?string { return $this->body; }
    public function getIcon(): ?string { return $this->icon; }
    public function getIconColor(): ?string { return $this->iconColor; }
    public function getActionUrl(): ?string { return $this->actionUrl; }
    public function getActionLabel(): ?string { return $this->actionLabel; }
    public function getNotifiableType(): ?string { return $this->notifiableType; }
    public function getNotifiableId(): ?int { return $this->notifiableId; }
    public function getData(): array { return $this->data; }
    public function getReadAt(): ?DateTimeImmutable { return $this->readAt; }
    public function getArchivedAt(): ?DateTimeImmutable { return $this->archivedAt; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }

    public function equals(Entity $other): bool
    {
        return $other instanceof self
            && $this->id !== null
            && $other->id !== null
            && $this->id === $other->id;
    }

    // Domain actions
    public function markAsRead(): void
    {
        if ($this->readAt === null) {
            $this->readAt = new DateTimeImmutable();
            $this->updatedAt = new DateTimeImmutable();
        }
    }

    public function archive(): void
    {
        if ($this->archivedAt === null) {
            $this->archivedAt = new DateTimeImmutable();
            $this->updatedAt = new DateTimeImmutable();
        }
    }

    // State checks
    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    // Helper methods
    public static function getCategoryFromType(string $type): string
    {
        $parts = explode('.', $type);
        $prefix = $parts[0] ?? 'system';

        return match ($prefix) {
            'approval' => self::CATEGORY_APPROVALS,
            'assignment' => self::CATEGORY_ASSIGNMENTS,
            'mention' => self::CATEGORY_MENTIONS,
            'record' => self::CATEGORY_UPDATES,
            'reminder' => self::CATEGORY_REMINDERS,
            'deal' => self::CATEGORY_DEALS,
            'task' => self::CATEGORY_TASKS,
            default => self::CATEGORY_SYSTEM,
        };
    }

    public static function getIconDefaults(string $type): array
    {
        return match ($type) {
            self::TYPE_APPROVAL_PENDING => ['icon' => 'clock', 'color' => 'yellow'],
            self::TYPE_APPROVAL_APPROVED => ['icon' => 'check-circle', 'color' => 'green'],
            self::TYPE_APPROVAL_REJECTED => ['icon' => 'x-circle', 'color' => 'red'],
            self::TYPE_APPROVAL_ESCALATED => ['icon' => 'arrow-up-circle', 'color' => 'orange'],
            self::TYPE_APPROVAL_REMINDER => ['icon' => 'bell', 'color' => 'yellow'],
            self::TYPE_ASSIGNMENT_NEW => ['icon' => 'user-plus', 'color' => 'blue'],
            self::TYPE_ASSIGNMENT_CHANGED => ['icon' => 'users', 'color' => 'blue'],
            self::TYPE_MENTION_COMMENT, self::TYPE_MENTION_NOTE => ['icon' => 'at-sign', 'color' => 'purple'],
            self::TYPE_RECORD_UPDATED => ['icon' => 'edit', 'color' => 'gray'],
            self::TYPE_RECORD_DELETED => ['icon' => 'trash', 'color' => 'red'],
            self::TYPE_REMINDER_TASK, self::TYPE_REMINDER_ACTIVITY, self::TYPE_REMINDER_FOLLOWUP => ['icon' => 'bell', 'color' => 'yellow'],
            self::TYPE_DEAL_WON => ['icon' => 'trophy', 'color' => 'green'],
            self::TYPE_DEAL_LOST => ['icon' => 'thumbs-down', 'color' => 'red'],
            self::TYPE_DEAL_STAGE_CHANGED => ['icon' => 'arrow-right', 'color' => 'blue'],
            self::TYPE_TASK_ASSIGNED => ['icon' => 'clipboard', 'color' => 'blue'],
            self::TYPE_TASK_COMPLETED => ['icon' => 'check-square', 'color' => 'green'],
            self::TYPE_TASK_OVERDUE => ['icon' => 'alert-triangle', 'color' => 'red'],
            default => ['icon' => 'bell', 'color' => 'gray'],
        };
    }

    /**
     * Convert to array for API responses.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'type' => $this->type,
            'category' => $this->category,
            'title' => $this->title,
            'body' => $this->body,
            'icon' => $this->icon,
            'icon_color' => $this->iconColor,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
            'notifiable_type' => $this->notifiableType,
            'notifiable_id' => $this->notifiableId,
            'data' => $this->data,
            'read_at' => $this->readAt?->format('Y-m-d H:i:s'),
            'archived_at' => $this->archivedAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
