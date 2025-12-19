<?php

declare(strict_types=1);

namespace App\Application\Services\Activity;

use App\Domain\Activity\Repositories\ActivityRepositoryInterface;
use App\Models\Activity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityApplicationService
{
    public function __construct(
        private ActivityRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // QUERY USE CASES
    // =========================================================================

    /**
     * List activities with filtering and pagination.
     */
    public function listActivities(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Activity::query()
            ->with(['user:id,name,email']);

        // Filter by type
        if (!empty($filters['type'])) {
            $query->ofType($filters['type']);
        }

        // Filter by types (multiple)
        if (!empty($filters['types']) && is_array($filters['types'])) {
            $query->whereIn('type', $filters['types']);
        }

        // Filter by subject
        if (!empty($filters['subject_type']) && !empty($filters['subject_id'])) {
            $query->forSubject($filters['subject_type'], $filters['subject_id']);
        }

        // Filter by user
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filter by date range
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        // Filter by scheduled date range
        if (!empty($filters['scheduled_from'])) {
            $query->where('scheduled_at', '>=', $filters['scheduled_from']);
        }
        if (!empty($filters['scheduled_to'])) {
            $query->where('scheduled_at', '<=', $filters['scheduled_to']);
        }

        // Filter by completion status
        if (isset($filters['completed'])) {
            if ($filters['completed']) {
                $query->whereNotNull('completed_at');
            } else {
                $query->whereNull('completed_at');
            }
        }

        // Filter by pinned
        if (!empty($filters['pinned'])) {
            $query->pinned();
        }

        // Filter by system/user activities
        if (isset($filters['is_system'])) {
            if ($filters['is_system']) {
                $query->systemActivities();
            } else {
                $query->userActivities();
            }
        }

        // Search in title/description/content
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a single activity by ID.
     */
    public function getActivity(int $id): ?Activity
    {
        return Activity::with(['user:id,name,email', 'subject', 'related'])->find($id);
    }

    /**
     * Get timeline for a subject (polymorphic entity).
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
     * Get upcoming scheduled activities for a user.
     */
    public function getUpcoming(?int $userId = null, int $days = 7): Collection
    {
        $query = Activity::upcoming()
            ->with(['user:id,name,email', 'subject'])
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
            ->with(['user:id,name,email', 'subject'])
            ->orderBy('scheduled_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Get activity statistics for a subject.
     */
    public function getActivityStats(Model $subject): array
    {
        $subjectType = get_class($subject);
        $subjectId = $subject->getKey();

        $stats = Activity::forSubject($subjectType, $subjectId)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $completedTasks = Activity::forSubject($subjectType, $subjectId)
            ->whereIn('type', [Activity::TYPE_TASK, Activity::TYPE_CALL, Activity::TYPE_MEETING])
            ->whereNotNull('completed_at')
            ->count();

        $pendingTasks = Activity::forSubject($subjectType, $subjectId)
            ->whereIn('type', [Activity::TYPE_TASK, Activity::TYPE_CALL, Activity::TYPE_MEETING])
            ->whereNull('completed_at')
            ->whereNotNull('scheduled_at')
            ->count();

        $overdueCount = Activity::forSubject($subjectType, $subjectId)
            ->overdue()
            ->count();

        return [
            'by_type' => $stats,
            'total' => array_sum($stats),
            'completed' => $completedTasks,
            'pending' => $pendingTasks,
            'overdue' => $overdueCount,
        ];
    }

    /**
     * Get daily activity count for dashboard.
     */
    public function getDailyActivityCount(?int $userId = null, int $days = 30): Collection
    {
        $query = Activity::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    // =========================================================================
    // COMMAND USE CASES - NOTES
    // =========================================================================

    /**
     * Create a note on a record.
     */
    public function createNote(
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
     * Update an existing note.
     */
    public function updateNote(int $id, array $data): Activity
    {
        $activity = Activity::findOrFail($id);

        if ($activity->type !== Activity::TYPE_NOTE) {
            throw new \InvalidArgumentException('Activity is not a note');
        }

        $activity->update([
            'title' => $data['title'] ?? $activity->title,
            'content' => $data['content'] ?? $activity->content,
            'is_internal' => $data['is_internal'] ?? $activity->is_internal,
            'is_pinned' => $data['is_pinned'] ?? $activity->is_pinned,
        ]);

        return $activity->fresh();
    }

    // =========================================================================
    // COMMAND USE CASES - CALLS
    // =========================================================================

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
     * Schedule a call.
     */
    public function scheduleCall(
        Model $subject,
        string $title,
        \DateTimeInterface $scheduledAt,
        ?string $description = null,
        ?int $durationMinutes = null
    ): Activity {
        return $this->logCall($subject, $title, $description, null, $durationMinutes, $scheduledAt);
    }

    /**
     * Complete a scheduled call.
     */
    public function completeCall(
        int $activityId,
        string $outcome,
        ?int $durationMinutes = null,
        ?string $notes = null
    ): Activity {
        $activity = Activity::findOrFail($activityId);

        if ($activity->type !== Activity::TYPE_CALL) {
            throw new \InvalidArgumentException('Activity is not a call');
        }

        $activity->update([
            'action' => Activity::ACTION_COMPLETED,
            'outcome' => $outcome,
            'duration_minutes' => $durationMinutes ?? $activity->duration_minutes,
            'description' => $notes ?? $activity->description,
            'completed_at' => now(),
        ]);

        return $activity->fresh();
    }

    // =========================================================================
    // COMMAND USE CASES - MEETINGS
    // =========================================================================

    /**
     * Schedule a meeting.
     */
    public function scheduleMeeting(
        Model $subject,
        string $title,
        \DateTimeInterface $scheduledAt,
        ?string $description = null,
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
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => $durationMinutes,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Complete a scheduled meeting.
     */
    public function completeMeeting(
        int $activityId,
        ?string $outcome = null,
        ?string $notes = null,
        ?int $actualDuration = null
    ): Activity {
        $activity = Activity::findOrFail($activityId);

        if ($activity->type !== Activity::TYPE_MEETING) {
            throw new \InvalidArgumentException('Activity is not a meeting');
        }

        $activity->update([
            'action' => Activity::ACTION_COMPLETED,
            'outcome' => $outcome ?? Activity::OUTCOME_COMPLETED,
            'description' => $notes ?? $activity->description,
            'duration_minutes' => $actualDuration ?? $activity->duration_minutes,
            'completed_at' => now(),
        ]);

        return $activity->fresh();
    }

    /**
     * Cancel a scheduled meeting.
     */
    public function cancelMeeting(int $activityId, ?string $reason = null): Activity
    {
        $activity = Activity::findOrFail($activityId);

        if ($activity->type !== Activity::TYPE_MEETING) {
            throw new \InvalidArgumentException('Activity is not a meeting');
        }

        $activity->update([
            'action' => Activity::ACTION_CANCELLED,
            'outcome' => Activity::OUTCOME_CANCELLED,
            'description' => $reason ? ($activity->description . "\n\nCancellation reason: " . $reason) : $activity->description,
        ]);

        return $activity->fresh();
    }

    /**
     * Reschedule a meeting.
     */
    public function rescheduleMeeting(int $activityId, \DateTimeInterface $newScheduledAt, ?string $reason = null): Activity
    {
        $activity = Activity::findOrFail($activityId);

        if ($activity->type !== Activity::TYPE_MEETING) {
            throw new \InvalidArgumentException('Activity is not a meeting');
        }

        $oldScheduledAt = $activity->scheduled_at;

        $activity->update([
            'scheduled_at' => $newScheduledAt,
            'outcome' => Activity::OUTCOME_RESCHEDULED,
            'metadata' => array_merge($activity->metadata ?? [], [
                'rescheduled_from' => $oldScheduledAt?->toIso8601String(),
                'reschedule_reason' => $reason,
            ]),
        ]);

        return $activity->fresh();
    }

    // =========================================================================
    // COMMAND USE CASES - TASKS
    // =========================================================================

    /**
     * Create a task.
     */
    public function createTask(
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
     * Complete a task.
     */
    public function completeTask(int $activityId, ?string $notes = null): Activity
    {
        $activity = Activity::findOrFail($activityId);

        if ($activity->type !== Activity::TYPE_TASK) {
            throw new \InvalidArgumentException('Activity is not a task');
        }

        $activity->update([
            'action' => Activity::ACTION_COMPLETED,
            'completed_at' => now(),
            'outcome' => Activity::OUTCOME_COMPLETED,
            'description' => $notes ?? $activity->description,
        ]);

        return $activity->fresh();
    }

    /**
     * Reopen a completed task.
     */
    public function reopenTask(int $activityId): Activity
    {
        $activity = Activity::findOrFail($activityId);

        if ($activity->type !== Activity::TYPE_TASK) {
            throw new \InvalidArgumentException('Activity is not a task');
        }

        $activity->update([
            'action' => Activity::ACTION_CREATED,
            'completed_at' => null,
            'outcome' => null,
        ]);

        return $activity->fresh();
    }

    // =========================================================================
    // COMMAND USE CASES - EMAILS
    // =========================================================================

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

    // =========================================================================
    // COMMAND USE CASES - SYSTEM ACTIVITIES
    // =========================================================================

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
    public function logFieldUpdate(Model $subject, array $changes): Activity
    {
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

    // =========================================================================
    // COMMAND USE CASES - GENERAL
    // =========================================================================

    /**
     * Update an activity.
     */
    public function updateActivity(int $id, array $data): Activity
    {
        $activity = Activity::findOrFail($id);

        $activity->update([
            'title' => $data['title'] ?? $activity->title,
            'description' => $data['description'] ?? $activity->description,
            'content' => $data['content'] ?? $activity->content,
            'scheduled_at' => $data['scheduled_at'] ?? $activity->scheduled_at,
            'is_pinned' => $data['is_pinned'] ?? $activity->is_pinned,
            'is_internal' => $data['is_internal'] ?? $activity->is_internal,
        ]);

        return $activity->fresh();
    }

    /**
     * Delete an activity.
     */
    public function deleteActivity(int $id): bool
    {
        $activity = Activity::findOrFail($id);
        return $activity->delete();
    }

    /**
     * Toggle pin status for an activity.
     */
    public function togglePin(int $id): Activity
    {
        $activity = Activity::findOrFail($id);
        $activity->togglePin();
        return $activity->fresh();
    }

    /**
     * Mark activity as completed.
     */
    public function markCompleted(int $id, ?string $outcome = null): Activity
    {
        $activity = Activity::findOrFail($id);
        $activity->markCompleted($outcome);
        return $activity->fresh();
    }

    /**
     * Bulk delete activities.
     */
    public function bulkDelete(array $ids): int
    {
        return Activity::whereIn('id', $ids)->delete();
    }

    /**
     * Bulk complete activities.
     */
    public function bulkComplete(array $ids, ?string $outcome = null): int
    {
        return Activity::whereIn('id', $ids)
            ->whereNull('completed_at')
            ->update([
                'completed_at' => now(),
                'outcome' => $outcome ?? Activity::OUTCOME_COMPLETED,
            ]);
    }
}
