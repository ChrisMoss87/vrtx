<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Activity;

use App\Domain\Activity\Entities\Activity as ActivityEntity;
use App\Domain\Activity\Repositories\ActivityRepositoryInterface;
use App\Domain\Activity\ValueObjects\ActivityAction;
use App\Domain\Activity\ValueObjects\ActivityOutcome;
use App\Domain\Activity\ValueObjects\ActivityType;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class EloquentActivityRepository implements ActivityRepositoryInterface
{
    private const TABLE = 'activities';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?ActivityEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(ActivityEntity $activity): ActivityEntity
    {
        $data = $this->toRowData($activity);

        if ($activity->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $activity->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $activity->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        // Soft delete
        $affected = DB::table(self::TABLE)
            ->where('id', $id)
            ->update(['deleted_at' => now()]);

        return $affected > 0;
    }

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public function findByIdWithRelations(int $id): ?array
    {
        $row = DB::table(self::TABLE)
            ->where(self::TABLE . '.id', $id)
            ->first();

        if (!$row) {
            return null;
        }

        $result = (array) $row;

        // Load user relation
        if ($row->user_id) {
            $user = DB::table('users')
                ->select('id', 'name', 'email')
                ->where('id', $row->user_id)
                ->first();
            $result['user'] = $user ? (array) $user : null;
        }

        return $result;
    }

    public function create(array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        return (array) DB::table(self::TABLE)->where('id', $id)->first();
    }

    public function update(int $id, array $data): array
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        return (array) DB::table(self::TABLE)->where('id', $id)->first();
    }

    public function bulkDelete(array $ids): int
    {
        return DB::table(self::TABLE)
            ->whereIn('id', $ids)
            ->update(['deleted_at' => now()]);
    }

    public function bulkUpdate(array $ids, array $data): int
    {
        return DB::table(self::TABLE)
            ->whereIn('id', $ids)
            ->update(array_merge($data, ['updated_at' => now()]));
    }

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    public function findWithFilters(array $filters, int $perPage = 25): PaginatedResult
    {
        $query = DB::table(self::TABLE)
            ->whereNull('deleted_at');

        // Filter by type
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filter by types (multiple)
        if (!empty($filters['types']) && is_array($filters['types'])) {
            $query->whereIn('type', $filters['types']);
        }

        // Filter by subject
        if (!empty($filters['subject_type']) && !empty($filters['subject_id'])) {
            $query->where('subject_type', $filters['subject_type'])
                ->where('subject_id', $filters['subject_id']);
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
            $query->where('is_pinned', true);
        }

        // Filter by system/user activities
        if (isset($filters['is_system'])) {
            $query->where('is_system', $filters['is_system']);
        }

        // Search in title/description/content
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhere('content', 'ilike', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Get total count
        $total = $query->count();

        // Get page
        $page = $filters['page'] ?? 1;
        $items = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn($row) => (array) $row)
            ->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function findForSubject(
        string $subjectType,
        int $subjectId,
        ?int $limit = null,
        ?string $type = null,
        bool $includeSystem = true
    ): array {
        $query = DB::table(self::TABLE)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('type', $type);
        }

        if (!$includeSystem) {
            $query->where('is_system', false);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->map(fn($row) => (array) $row)->all();
    }

    public function findUpcoming(?int $userId = null, int $days = 7): array
    {
        $query = DB::table(self::TABLE)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now())
            ->where('scheduled_at', '<=', now()->addDays($days))
            ->whereNull('completed_at')
            ->whereNull('deleted_at')
            ->orderBy('scheduled_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get()->map(fn($row) => (array) $row)->all();
    }

    public function findOverdue(?int $userId = null): array
    {
        $query = DB::table(self::TABLE)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', now())
            ->whereNull('completed_at')
            ->whereNull('deleted_at')
            ->orderBy('scheduled_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get()->map(fn($row) => (array) $row)->all();
    }

    public function getStatsBySubject(string $subjectType, int $subjectId): array
    {
        $baseQuery = fn() => DB::table(self::TABLE)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereNull('deleted_at');

        $stats = $baseQuery()
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $taskTypes = [
            ActivityRepositoryInterface::TYPE_TASK,
            ActivityRepositoryInterface::TYPE_CALL,
            ActivityRepositoryInterface::TYPE_MEETING,
        ];

        $completedTasks = $baseQuery()
            ->whereIn('type', $taskTypes)
            ->whereNotNull('completed_at')
            ->count();

        $pendingTasks = $baseQuery()
            ->whereIn('type', $taskTypes)
            ->whereNull('completed_at')
            ->whereNotNull('scheduled_at')
            ->count();

        $overdueCount = $baseQuery()
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', now())
            ->whereNull('completed_at')
            ->count();

        return [
            'by_type' => $stats,
            'total' => array_sum($stats),
            'completed' => $completedTasks,
            'pending' => $pendingTasks,
            'overdue' => $overdueCount,
        ];
    }

    public function getDailyCount(?int $userId = null, int $days = 30): array
    {
        $query = DB::table(self::TABLE)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNull('deleted_at')
            ->groupBy('date')
            ->orderBy('date');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get()->map(fn($row) => (array) $row)->all();
    }

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): ActivityEntity
    {
        return ActivityEntity::reconstitute(
            id: (int) $row->id,
            userId: $row->user_id ? (int) $row->user_id : null,
            type: ActivityType::from($row->type),
            action: $row->action ? ActivityAction::tryFrom($row->action) : null,
            subjectType: $row->subject_type,
            subjectId: $row->subject_id ? (int) $row->subject_id : null,
            relatedType: $row->related_type,
            relatedId: $row->related_id ? (int) $row->related_id : null,
            title: $row->title,
            description: $row->description,
            metadata: $row->metadata ? json_decode($row->metadata, true) : [],
            content: $row->content,
            isPinned: (bool) $row->is_pinned,
            scheduledAt: $row->scheduled_at ? new DateTimeImmutable($row->scheduled_at) : null,
            completedAt: $row->completed_at ? new DateTimeImmutable($row->completed_at) : null,
            durationMinutes: $row->duration_minutes ? (int) $row->duration_minutes : null,
            outcome: $row->outcome ? ActivityOutcome::tryFrom($row->outcome) : null,
            isInternal: (bool) $row->is_internal,
            isSystem: (bool) $row->is_system,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
            deletedAt: $row->deleted_at ? new DateTimeImmutable($row->deleted_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(ActivityEntity $activity): array
    {
        return [
            'user_id' => $activity->getUserId(),
            'type' => $activity->getType()->value,
            'action' => $activity->getAction()?->value,
            'subject_type' => $activity->getSubjectType(),
            'subject_id' => $activity->getSubjectId(),
            'related_type' => $activity->getRelatedType(),
            'related_id' => $activity->getRelatedId(),
            'title' => $activity->getTitle(),
            'description' => $activity->getDescription(),
            'metadata' => json_encode($activity->getMetadata()),
            'content' => $activity->getContent(),
            'is_pinned' => $activity->isPinned(),
            'scheduled_at' => $activity->getScheduledAt()?->format('Y-m-d H:i:s'),
            'completed_at' => $activity->getCompletedAt()?->format('Y-m-d H:i:s'),
            'duration_minutes' => $activity->getDurationMinutes(),
            'outcome' => $activity->getOutcome()?->value,
            'is_internal' => $activity->isInternal(),
            'is_system' => $activity->isSystem(),
        ];
    }
}
