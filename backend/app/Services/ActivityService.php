<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ActivityService
{
    /**
     * Log a note on a record.
     */
    public function logNote(
        Model $subject,
        string $content,
        ?string $title = null,
        bool $isInternal = false,
        bool $isPinned = false
    ): Activity {
        return Activity::create([
            'user_id' => Auth::id(),
            'type' => Activity::TYPE_NOTE,
            'action' => Activity::ACTION_CREATED,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'title' => $title ?? 'Note added',
            'content' => $content,
            'is_internal' => $isInternal,
            'is_pinned' => $isPinned,
        ]);
    }

    /**
     * Log a call activity.
     */
    public function logCall(
        Model $subject,
        string $title,
        ?string $description = null,
        ?string $outcome = null,
        ?int $durationMinutes = null,
        ?\DateTimeInterface $scheduledAt = null
    ): Activity {
        return Activity::create([
            'user_id' => Auth::id(),
            'type' => Activity::TYPE_CALL,
            'action' => $scheduledAt ? Activity::ACTION_SCHEDULED : Activity::ACTION_COMPLETED,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'title' => $title,
            'description' => $description,
            'outcome' => $outcome,
            'duration_minutes' => $durationMinutes,
            'scheduled_at' => $scheduledAt,
            'completed_at' => $scheduledAt ? null : now(),
        ]);
    }

    /**
     * Log a meeting activity.
     */
    public function logMeeting(
        Model $subject,
        string $title,
        ?string $description = null,
        ?\DateTimeInterface $scheduledAt = null,
        ?int $durationMinutes = null,
        ?array $metadata = null
    ): Activity {
        return Activity::create([
            'user_id' => Auth::id(),
            'type' => Activity::TYPE_MEETING,
            'action' => Activity::ACTION_SCHEDULED,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'title' => $title,
            'description' => $description,
            'scheduled_at' => $scheduledAt ?? now(),
            'duration_minutes' => $durationMinutes,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log a task activity.
     */
    public function logTask(
        Model $subject,
        string $title,
        ?string $description = null,
        ?\DateTimeInterface $dueAt = null
    ): Activity {
        return Activity::create([
            'user_id' => Auth::id(),
            'type' => Activity::TYPE_TASK,
            'action' => Activity::ACTION_CREATED,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'title' => $title,
            'description' => $description,
            'scheduled_at' => $dueAt,
        ]);
    }

    /**
     * Log an email activity.
     */
    public function logEmail(
        Model $subject,
        string $title,
        ?Model $emailMessage = null,
        string $action = 'sent'
    ): Activity {
        return Activity::create([
            'user_id' => Auth::id(),
            'type' => Activity::TYPE_EMAIL,
            'action' => $action,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'related_type' => $emailMessage ? get_class($emailMessage) : null,
            'related_id' => $emailMessage?->getKey(),
            'title' => $title,
            'is_system' => true,
        ]);
    }

    /**
     * Log a status change.
     */
    public function logStatusChange(
        Model $subject,
        string $field,
        mixed $oldValue,
        mixed $newValue
    ): Activity {
        return Activity::create([
            'user_id' => Auth::id(),
            'type' => Activity::TYPE_STATUS_CHANGE,
            'action' => Activity::ACTION_UPDATED,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'title' => "Changed {$field}",
            'description' => "From \"{$oldValue}\" to \"{$newValue}\"",
            'metadata' => [
                'field' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ],
            'is_system' => true,
        ]);
    }

    /**
     * Log field updates.
     */
    public function logFieldUpdate(
        Model $subject,
        array $changes
    ): Activity {
        $fieldCount = count($changes);
        $fieldNames = array_keys($changes);

        return Activity::create([
            'user_id' => Auth::id(),
            'type' => Activity::TYPE_FIELD_UPDATE,
            'action' => Activity::ACTION_UPDATED,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'title' => $fieldCount === 1
                ? "Updated {$fieldNames[0]}"
                : "Updated {$fieldCount} fields",
            'metadata' => ['changes' => $changes],
            'is_system' => true,
        ]);
    }

    /**
     * Log record creation.
     */
    public function logCreated(Model $subject, ?string $title = null): Activity
    {
        $modelName = class_basename($subject);

        return Activity::create([
            'user_id' => Auth::id(),
            'type' => Activity::TYPE_CREATED,
            'action' => Activity::ACTION_CREATED,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'title' => $title ?? "{$modelName} created",
            'is_system' => true,
        ]);
    }

    /**
     * Log record deletion.
     */
    public function logDeleted(Model $subject, ?string $title = null): Activity
    {
        $modelName = class_basename($subject);

        return Activity::create([
            'user_id' => Auth::id(),
            'type' => Activity::TYPE_DELETED,
            'action' => Activity::ACTION_DELETED,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'title' => $title ?? "{$modelName} deleted",
            'is_system' => true,
        ]);
    }

    /**
     * Log a comment.
     */
    public function logComment(
        Model $subject,
        string $content,
        ?Model $parentActivity = null
    ): Activity {
        return Activity::create([
            'user_id' => Auth::id(),
            'type' => Activity::TYPE_COMMENT,
            'action' => Activity::ACTION_CREATED,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'related_type' => $parentActivity ? get_class($parentActivity) : null,
            'related_id' => $parentActivity?->getKey(),
            'title' => 'Comment added',
            'content' => $content,
        ]);
    }

    /**
     * Log attachment added.
     */
    public function logAttachment(
        Model $subject,
        string $fileName,
        ?array $fileInfo = null
    ): Activity {
        return Activity::create([
            'user_id' => Auth::id(),
            'type' => Activity::TYPE_ATTACHMENT,
            'action' => Activity::ACTION_CREATED,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'title' => "Attachment added: {$fileName}",
            'metadata' => $fileInfo,
            'is_system' => true,
        ]);
    }

    /**
     * Get timeline for a subject.
     */
    public function getTimeline(
        Model $subject,
        ?int $limit = 50,
        ?string $type = null,
        bool $includeSystem = true
    ): Collection {
        $query = Activity::forSubject(get_class($subject), $subject->getKey())
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->ofType($type);
        }

        if (!$includeSystem) {
            $query->userActivities();
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get upcoming activities for a user.
     */
    public function getUpcoming(?int $userId = null, int $days = 7): Collection
    {
        $query = Activity::upcoming()
            ->with(['user:id,name,email'])
            ->where('scheduled_at', '<=', now()->addDays($days))
            ->orderBy('scheduled_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Get overdue activities for a user.
     */
    public function getOverdue(?int $userId = null): Collection
    {
        $query = Activity::overdue()
            ->with(['user:id,name,email'])
            ->orderBy('scheduled_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }
}
