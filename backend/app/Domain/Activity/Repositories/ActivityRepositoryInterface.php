<?php

declare(strict_types=1);

namespace App\Domain\Activity\Repositories;

use App\Domain\Activity\Entities\Activity;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface ActivityRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?Activity;

    public function save(Activity $activity): Activity;

    public function delete(int $id): bool;

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array;

    public function findByIdWithRelations(int $id): ?array;

    public function create(array $data): array;

    public function update(int $id, array $data): array;

    public function bulkDelete(array $ids): int;

    public function bulkUpdate(array $ids, array $data): int;

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * List activities with filters and pagination.
     */
    public function findWithFilters(array $filters, int $perPage = 25): PaginatedResult;

    /**
     * Get timeline for a subject (polymorphic entity).
     */
    public function findForSubject(
        string $subjectType,
        int $subjectId,
        ?int $limit = null,
        ?string $type = null,
        bool $includeSystem = true
    ): array;

    /**
     * Get upcoming scheduled activities.
     */
    public function findUpcoming(?int $userId = null, int $days = 7): array;

    /**
     * Get overdue activities.
     */
    public function findOverdue(?int $userId = null): array;

    /**
     * Get activity statistics for a subject.
     */
    public function getStatsBySubject(string $subjectType, int $subjectId): array;

    /**
     * Get daily activity count for dashboard.
     */
    public function getDailyCount(?int $userId = null, int $days = 30): array;

    // =========================================================================
    // ACTIVITY TYPE CONSTANTS
    // =========================================================================

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

    // =========================================================================
    // ACTION TYPE CONSTANTS
    // =========================================================================

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_COMPLETED = 'completed';
    public const ACTION_SENT = 'sent';
    public const ACTION_RECEIVED = 'received';
    public const ACTION_SCHEDULED = 'scheduled';
    public const ACTION_CANCELLED = 'cancelled';

    // =========================================================================
    // OUTCOME CONSTANTS
    // =========================================================================

    public const OUTCOME_COMPLETED = 'completed';
    public const OUTCOME_NO_ANSWER = 'no_answer';
    public const OUTCOME_LEFT_VOICEMAIL = 'left_voicemail';
    public const OUTCOME_BUSY = 'busy';
    public const OUTCOME_WRONG_NUMBER = 'wrong_number';
    public const OUTCOME_RESCHEDULED = 'rescheduled';
    public const OUTCOME_CANCELLED = 'cancelled';
}
