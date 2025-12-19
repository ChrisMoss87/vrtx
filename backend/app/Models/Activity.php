<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use HasFactory, SoftDeletes;

    // Activity types
    public const TYPE_NOTE = 'note';
    public const TYPE_CALL = 'call';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_TASK = 'task';
    public const TYPE_EMAIL = 'email';
    public const TYPE_STATUS_CHANGE = 'status_change';
    public const TYPE_FIELD_UPDATE = 'field_update';
    public const TYPE_COMMENT = 'comment';
    public const TYPE_ATTACHMENT = 'attachment';
    public const TYPE_CREATED = 'created';
    public const TYPE_DELETED = 'deleted';

    // Action types
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_COMPLETED = 'completed';
    public const ACTION_SENT = 'sent';
    public const ACTION_RECEIVED = 'received';
    public const ACTION_SCHEDULED = 'scheduled';
    public const ACTION_CANCELLED = 'cancelled';

    // Call/meeting outcomes
    public const OUTCOME_COMPLETED = 'completed';
    public const OUTCOME_NO_ANSWER = 'no_answer';
    public const OUTCOME_LEFT_VOICEMAIL = 'left_voicemail';
    public const OUTCOME_BUSY = 'busy';
    public const OUTCOME_WRONG_NUMBER = 'wrong_number';
    public const OUTCOME_RESCHEDULED = 'rescheduled';
    public const OUTCOME_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'type',
        'action',
        'subject_type',
        'subject_id',
        'related_type',
        'related_id',
        'title',
        'description',
        'metadata',
        'content',
        'is_pinned',
        'scheduled_at',
        'completed_at',
        'duration_minutes',
        'outcome',
        'is_internal',
        'is_system',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_pinned' => 'boolean',
        'is_internal' => 'boolean',
        'is_system' => 'boolean',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    protected $attributes = [
        'is_pinned' => false,
        'is_internal' => false,
        'is_system' => false,
    ];

    /**
     * Get the user who created the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subject of the activity (polymorphic).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the related entity (polymorphic).
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope for a specific subject.
     */
    public function scopeForSubject($query, string $type, int $id)
    {
        return $query->where('subject_type', $type)
            ->where('subject_id', $id);
    }

    /**
     * Scope for a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for user-created activities (not system).
     */
    public function scopeUserActivities($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope for system activities.
     */
    public function scopeSystemActivities($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope for pinned activities.
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope for upcoming scheduled activities.
     */
    public function scopeUpcoming($query)
    {
        return $query->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now())
            ->whereNull('completed_at');
    }

    /**
     * Scope for overdue activities.
     */
    public function scopeOverdue($query)
    {
        return $query->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', now())
            ->whereNull('completed_at');
    }

    /**
     * Check if activity is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->scheduled_at
            && $this->scheduled_at->isPast()
            && !$this->completed_at;
    }

    /**
     * Mark activity as completed.
     */
    public function markCompleted(?string $outcome = null): void
    {
        $this->update([
            'completed_at' => now(),
            'outcome' => $outcome ?? self::OUTCOME_COMPLETED,
        ]);
    }

    /**
     * Toggle pinned status.
     */
    public function togglePin(): void
    {
        $this->update(['is_pinned' => !$this->is_pinned]);
    }

    /**
     * Get icon for activity type.
     */
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_NOTE => 'sticky-note',
            self::TYPE_CALL => 'phone',
            self::TYPE_MEETING => 'calendar',
            self::TYPE_TASK => 'check-square',
            self::TYPE_EMAIL => 'mail',
            self::TYPE_STATUS_CHANGE => 'git-branch',
            self::TYPE_FIELD_UPDATE => 'edit',
            self::TYPE_COMMENT => 'message-circle',
            self::TYPE_ATTACHMENT => 'paperclip',
            self::TYPE_CREATED => 'plus-circle',
            self::TYPE_DELETED => 'trash',
            default => 'activity',
        };
    }

    /**
     * Get color for activity type.
     */
    public function getColorAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_NOTE => 'yellow',
            self::TYPE_CALL => 'blue',
            self::TYPE_MEETING => 'purple',
            self::TYPE_TASK => 'green',
            self::TYPE_EMAIL => 'cyan',
            self::TYPE_STATUS_CHANGE => 'orange',
            self::TYPE_FIELD_UPDATE => 'gray',
            self::TYPE_COMMENT => 'pink',
            self::TYPE_ATTACHMENT => 'indigo',
            self::TYPE_CREATED => 'green',
            self::TYPE_DELETED => 'red',
            default => 'gray',
        };
    }

    /**
     * Get all available activity types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_NOTE => 'Note',
            self::TYPE_CALL => 'Call',
            self::TYPE_MEETING => 'Meeting',
            self::TYPE_TASK => 'Task',
            self::TYPE_EMAIL => 'Email',
            self::TYPE_STATUS_CHANGE => 'Status Change',
            self::TYPE_FIELD_UPDATE => 'Field Update',
            self::TYPE_COMMENT => 'Comment',
            self::TYPE_ATTACHMENT => 'Attachment',
            self::TYPE_CREATED => 'Created',
            self::TYPE_DELETED => 'Deleted',
        ];
    }

    /**
     * Get all available outcomes.
     */
    public static function getOutcomes(): array
    {
        return [
            self::OUTCOME_COMPLETED => 'Completed',
            self::OUTCOME_NO_ANSWER => 'No Answer',
            self::OUTCOME_LEFT_VOICEMAIL => 'Left Voicemail',
            self::OUTCOME_BUSY => 'Busy',
            self::OUTCOME_WRONG_NUMBER => 'Wrong Number',
            self::OUTCOME_RESCHEDULED => 'Rescheduled',
            self::OUTCOME_CANCELLED => 'Cancelled',
        ];
    }
}
