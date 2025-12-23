<?php

declare(strict_types=1);

namespace App\Application\Services\Activity;

use App\Domain\Activity\Repositories\ActivityRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeInterface;

class ActivityApplicationService
{
    public function __construct(
        private ActivityRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES
    // =========================================================================

    /**
     * List activities with filtering and pagination.
     */
    public function listActivities(array $filters = [], int $perPage = 25): PaginatedResult
    {
        return $this->repository->findWithFilters($filters, $perPage);
    }

    /**
     * Get a single activity by ID.
     */
    public function getActivity(int $id): ?array
    {
        return $this->repository->findByIdWithRelations($id);
    }

    /**
     * Get timeline for a subject (polymorphic entity).
     */
    public function getTimeline(
        string $subjectType,
        int $subjectId,
        ?int $limit = 50,
        ?string $type = null,
        bool $includeSystem = true
    ): array {
        return $this->repository->findForSubject(
            $subjectType,
            $subjectId,
            $limit,
            $type,
            $includeSystem
        );
    }

    /**
     * Get upcoming scheduled activities for a user.
     */
    public function getUpcoming(?int $userId = null, int $days = 7): array
    {
        return $this->repository->findUpcoming($userId, $days);
    }

    /**
     * Get overdue activities for a user.
     */
    public function getOverdue(?int $userId = null): array
    {
        return $this->repository->findOverdue($userId);
    }

    /**
     * Get activity statistics for a subject.
     */
    public function getActivityStats(string $subjectType, int $subjectId): array
    {
        return $this->repository->getStatsBySubject($subjectType, $subjectId);
    }

    /**
     * Get daily activity count for dashboard.
     */
    public function getDailyActivityCount(?int $userId = null, int $days = 30): array
    {
        return $this->repository->getDailyCount($userId, $days);
    }

    // =========================================================================
    // COMMAND USE CASES - NOTES
    // =========================================================================

    /**
     * Create a note on a record.
     */
    public function createNote(
        string $subjectType,
        int $subjectId,
        string $content,
        ?string $title = null,
        bool $isInternal = false,
        bool $isPinned = false
    ): array {
        return $this->repository->create([
            'user_id' => $this->authContext->userId(),
            'type' => ActivityRepositoryInterface::TYPE_NOTE,
            'action' => ActivityRepositoryInterface::ACTION_CREATED,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'title' => $title ?? 'Note added',
            'content' => $content,
            'is_internal' => $isInternal,
            'is_pinned' => $isPinned,
        ]);
    }

    /**
     * Update an existing note.
     */
    public function updateNote(int $id, array $data): array
    {
        $activity = $this->repository->findById($id);

        if (!$activity || $activity['type'] !== ActivityRepositoryInterface::TYPE_NOTE) {
            throw new \InvalidArgumentException('Activity is not a note');
        }

        return $this->repository->update($id, [
            'title' => $data['title'] ?? $activity['title'],
            'content' => $data['content'] ?? $activity['content'],
            'is_internal' => $data['is_internal'] ?? $activity['is_internal'],
            'is_pinned' => $data['is_pinned'] ?? $activity['is_pinned'],
        ]);
    }

    // =========================================================================
    // COMMAND USE CASES - CALLS
    // =========================================================================

    /**
     * Log a call activity.
     */
    public function logCall(
        string $subjectType,
        int $subjectId,
        string $title,
        ?string $description = null,
        ?string $outcome = null,
        ?int $durationMinutes = null,
        ?DateTimeInterface $scheduledAt = null
    ): array {
        return $this->repository->create([
            'user_id' => $this->authContext->userId(),
            'type' => ActivityRepositoryInterface::TYPE_CALL,
            'action' => $scheduledAt
                ? ActivityRepositoryInterface::ACTION_SCHEDULED
                : ActivityRepositoryInterface::ACTION_COMPLETED,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
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
        string $subjectType,
        int $subjectId,
        string $title,
        DateTimeInterface $scheduledAt,
        ?string $description = null,
        ?int $durationMinutes = null
    ): array {
        return $this->logCall(
            $subjectType,
            $subjectId,
            $title,
            $description,
            null,
            $durationMinutes,
            $scheduledAt
        );
    }

    /**
     * Complete a scheduled call.
     */
    public function completeCall(
        int $activityId,
        string $outcome,
        ?int $durationMinutes = null,
        ?string $notes = null
    ): array {
        $activity = $this->repository->findById($activityId);

        if (!$activity || $activity['type'] !== ActivityRepositoryInterface::TYPE_CALL) {
            throw new \InvalidArgumentException('Activity is not a call');
        }

        return $this->repository->update($activityId, [
            'action' => ActivityRepositoryInterface::ACTION_COMPLETED,
            'outcome' => $outcome,
            'duration_minutes' => $durationMinutes ?? $activity['duration_minutes'],
            'description' => $notes ?? $activity['description'],
            'completed_at' => now(),
        ]);
    }

    // =========================================================================
    // COMMAND USE CASES - MEETINGS
    // =========================================================================

    /**
     * Schedule a meeting.
     */
    public function scheduleMeeting(
        string $subjectType,
        int $subjectId,
        string $title,
        DateTimeInterface $scheduledAt,
        ?string $description = null,
        ?int $durationMinutes = null,
        ?array $metadata = null
    ): array {
        return $this->repository->create([
            'user_id' => $this->authContext->userId(),
            'type' => ActivityRepositoryInterface::TYPE_MEETING,
            'action' => ActivityRepositoryInterface::ACTION_SCHEDULED,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
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
    ): array {
        $activity = $this->repository->findById($activityId);

        if (!$activity || $activity['type'] !== ActivityRepositoryInterface::TYPE_MEETING) {
            throw new \InvalidArgumentException('Activity is not a meeting');
        }

        return $this->repository->update($activityId, [
            'action' => ActivityRepositoryInterface::ACTION_COMPLETED,
            'outcome' => $outcome ?? ActivityRepositoryInterface::OUTCOME_COMPLETED,
            'description' => $notes ?? $activity['description'],
            'duration_minutes' => $actualDuration ?? $activity['duration_minutes'],
            'completed_at' => now(),
        ]);
    }

    /**
     * Cancel a scheduled meeting.
     */
    public function cancelMeeting(int $activityId, ?string $reason = null): array
    {
        $activity = $this->repository->findById($activityId);

        if (!$activity || $activity['type'] !== ActivityRepositoryInterface::TYPE_MEETING) {
            throw new \InvalidArgumentException('Activity is not a meeting');
        }

        $description = $activity['description'];
        if ($reason) {
            $description = $description . "\n\nCancellation reason: " . $reason;
        }

        return $this->repository->update($activityId, [
            'action' => ActivityRepositoryInterface::ACTION_CANCELLED,
            'outcome' => ActivityRepositoryInterface::OUTCOME_CANCELLED,
            'description' => $description,
        ]);
    }

    /**
     * Reschedule a meeting.
     */
    public function rescheduleMeeting(
        int $activityId,
        DateTimeInterface $newScheduledAt,
        ?string $reason = null
    ): array {
        $activity = $this->repository->findById($activityId);

        if (!$activity || $activity['type'] !== ActivityRepositoryInterface::TYPE_MEETING) {
            throw new \InvalidArgumentException('Activity is not a meeting');
        }

        $metadata = $activity['metadata'] ?? [];
        $metadata['rescheduled_from'] = $activity['scheduled_at'];
        $metadata['reschedule_reason'] = $reason;

        return $this->repository->update($activityId, [
            'scheduled_at' => $newScheduledAt,
            'outcome' => ActivityRepositoryInterface::OUTCOME_RESCHEDULED,
            'metadata' => $metadata,
        ]);
    }

    // =========================================================================
    // COMMAND USE CASES - TASKS
    // =========================================================================

    /**
     * Create a task.
     */
    public function createTask(
        string $subjectType,
        int $subjectId,
        string $title,
        ?string $description = null,
        ?DateTimeInterface $dueAt = null
    ): array {
        return $this->repository->create([
            'user_id' => $this->authContext->userId(),
            'type' => ActivityRepositoryInterface::TYPE_TASK,
            'action' => ActivityRepositoryInterface::ACTION_CREATED,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'title' => $title,
            'description' => $description,
            'scheduled_at' => $dueAt,
        ]);
    }

    /**
     * Complete a task.
     */
    public function completeTask(int $activityId, ?string $notes = null): array
    {
        $activity = $this->repository->findById($activityId);

        if (!$activity || $activity['type'] !== ActivityRepositoryInterface::TYPE_TASK) {
            throw new \InvalidArgumentException('Activity is not a task');
        }

        return $this->repository->update($activityId, [
            'action' => ActivityRepositoryInterface::ACTION_COMPLETED,
            'completed_at' => now(),
            'outcome' => ActivityRepositoryInterface::OUTCOME_COMPLETED,
            'description' => $notes ?? $activity['description'],
        ]);
    }

    /**
     * Reopen a completed task.
     */
    public function reopenTask(int $activityId): array
    {
        $activity = $this->repository->findById($activityId);

        if (!$activity || $activity['type'] !== ActivityRepositoryInterface::TYPE_TASK) {
            throw new \InvalidArgumentException('Activity is not a task');
        }

        return $this->repository->update($activityId, [
            'action' => ActivityRepositoryInterface::ACTION_CREATED,
            'completed_at' => null,
            'outcome' => null,
        ]);
    }

    // =========================================================================
    // COMMAND USE CASES - EMAILS
    // =========================================================================

    /**
     * Log an email activity.
     */
    public function logEmail(
        string $subjectType,
        int $subjectId,
        string $title,
        ?string $relatedType = null,
        ?int $relatedId = null,
        string $action = 'sent'
    ): array {
        return $this->repository->create([
            'user_id' => $this->authContext->userId(),
            'type' => ActivityRepositoryInterface::TYPE_EMAIL,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
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
        string $subjectType,
        int $subjectId,
        string $field,
        mixed $oldValue,
        mixed $newValue
    ): array {
        return $this->repository->create([
            'user_id' => $this->authContext->userId(),
            'type' => ActivityRepositoryInterface::TYPE_STATUS_CHANGE,
            'action' => ActivityRepositoryInterface::ACTION_UPDATED,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
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
        string $subjectType,
        int $subjectId,
        array $changes
    ): array {
        $fieldCount = count($changes);
        $fieldNames = array_keys($changes);

        return $this->repository->create([
            'user_id' => $this->authContext->userId(),
            'type' => ActivityRepositoryInterface::TYPE_FIELD_UPDATE,
            'action' => ActivityRepositoryInterface::ACTION_UPDATED,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
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
    public function logCreated(
        string $subjectType,
        int $subjectId,
        ?string $title = null
    ): array {
        $modelName = class_basename($subjectType);

        return $this->repository->create([
            'user_id' => $this->authContext->userId(),
            'type' => ActivityRepositoryInterface::TYPE_CREATED,
            'action' => ActivityRepositoryInterface::ACTION_CREATED,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'title' => $title ?? "{$modelName} created",
            'is_system' => true,
        ]);
    }

    /**
     * Log record deletion.
     */
    public function logDeleted(
        string $subjectType,
        int $subjectId,
        ?string $title = null
    ): array {
        $modelName = class_basename($subjectType);

        return $this->repository->create([
            'user_id' => $this->authContext->userId(),
            'type' => ActivityRepositoryInterface::TYPE_DELETED,
            'action' => ActivityRepositoryInterface::ACTION_DELETED,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'title' => $title ?? "{$modelName} deleted",
            'is_system' => true,
        ]);
    }

    /**
     * Log a comment.
     */
    public function logComment(
        string $subjectType,
        int $subjectId,
        string $content,
        ?string $parentActivityType = null,
        ?int $parentActivityId = null
    ): array {
        return $this->repository->create([
            'user_id' => $this->authContext->userId(),
            'type' => ActivityRepositoryInterface::TYPE_COMMENT,
            'action' => ActivityRepositoryInterface::ACTION_CREATED,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'related_type' => $parentActivityType,
            'related_id' => $parentActivityId,
            'title' => 'Comment added',
            'content' => $content,
        ]);
    }

    /**
     * Log attachment added.
     */
    public function logAttachment(
        string $subjectType,
        int $subjectId,
        string $fileName,
        ?array $fileInfo = null
    ): array {
        return $this->repository->create([
            'user_id' => $this->authContext->userId(),
            'type' => ActivityRepositoryInterface::TYPE_ATTACHMENT,
            'action' => ActivityRepositoryInterface::ACTION_CREATED,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
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
    public function updateActivity(int $id, array $data): array
    {
        $activity = $this->repository->findById($id);

        if (!$activity) {
            throw new \InvalidArgumentException('Activity not found');
        }

        return $this->repository->update($id, [
            'title' => $data['title'] ?? $activity['title'],
            'description' => $data['description'] ?? $activity['description'],
            'content' => $data['content'] ?? $activity['content'],
            'scheduled_at' => $data['scheduled_at'] ?? $activity['scheduled_at'],
            'is_pinned' => $data['is_pinned'] ?? $activity['is_pinned'],
            'is_internal' => $data['is_internal'] ?? $activity['is_internal'],
        ]);
    }

    /**
     * Delete an activity.
     */
    public function deleteActivity(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Toggle pin status for an activity.
     */
    public function togglePin(int $id): array
    {
        $activity = $this->repository->findById($id);

        if (!$activity) {
            throw new \InvalidArgumentException('Activity not found');
        }

        return $this->repository->update($id, [
            'is_pinned' => !$activity['is_pinned'],
        ]);
    }

    /**
     * Mark activity as completed.
     */
    public function markCompleted(int $id, ?string $outcome = null): array
    {
        return $this->repository->update($id, [
            'completed_at' => now(),
            'outcome' => $outcome ?? ActivityRepositoryInterface::OUTCOME_COMPLETED,
        ]);
    }

    /**
     * Bulk delete activities.
     */
    public function bulkDelete(array $ids): int
    {
        return $this->repository->bulkDelete($ids);
    }

    /**
     * Bulk complete activities.
     */
    public function bulkComplete(array $ids, ?string $outcome = null): int
    {
        return $this->repository->bulkUpdate($ids, [
            'completed_at' => now(),
            'outcome' => $outcome ?? ActivityRepositoryInterface::OUTCOME_COMPLETED,
        ]);
    }
}
