<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Model
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

    public const CATEGORIES = [
        self::CATEGORY_APPROVALS,
        self::CATEGORY_ASSIGNMENTS,
        self::CATEGORY_MENTIONS,
        self::CATEGORY_UPDATES,
        self::CATEGORY_REMINDERS,
        self::CATEGORY_DEALS,
        self::CATEGORY_TASKS,
        self::CATEGORY_SYSTEM,
    ];

    // Notification types (category.action format)
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

    protected $fillable = [
        'user_id',
        'type',
        'category',
        'title',
        'body',
        'icon',
        'icon_color',
        'action_url',
        'action_label',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
        'archived_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Accessors (read-only state checks - acceptable in models)
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Get the category from the notification type
     */
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

    /**
     * Get icon and color defaults for notification type
     */
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
}
