<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\AuditLog;

use App\Domain\AuditLog\Repositories\AuditLogRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Support\Facades\DB;

class DbAuditLogRepository implements AuditLogRepositoryInterface
{
    private const TABLE = 'audit_logs';

    public function findById(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $row ? $this->toArray($row) : null;
    }

    public function findByIdWithUser(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $result = $this->toArray($row);

        if ($row->user_id) {
            $user = DB::table('users')
                ->select('id', 'name', 'email')
                ->where('id', $row->user_id)
                ->first();
            $result['user'] = $user ? (array) $user : null;
        }

        return $result;
    }

    public function findWithFilters(array $filters, int $perPage = 25): PaginatedResult
    {
        $query = DB::table(self::TABLE)->orderBy('created_at', 'desc');

        if (!empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if (!empty($filters['auditable_id'])) {
            $query->where('auditable_id', $filters['auditable_id']);
        }

        if (!empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);
        }

        if (!empty($filters['tags']) && is_array($filters['tags'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['tags'] as $tag) {
                    $q->orWhereRaw("tags::jsonb ? ?", [$tag]);
                }
            });
        }

        $total = $query->count();
        $page = $filters['page'] ?? 1;

        $items = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn($row) => $this->toArrayWithUser($row))
            ->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function findForAuditable(string $auditableType, int $auditableId, int $limit = 50): array
    {
        return DB::table(self::TABLE)
            ->where('auditable_type', $auditableType)
            ->where('auditable_id', $auditableId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($row) => $this->toArrayWithUser($row))
            ->all();
    }

    public function findByUser(int $userId, ?string $startDate = null, ?string $endDate = null, int $perPage = 25): PaginatedResult
    {
        $query = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $total = $query->count();

        $items = $query
            ->limit($perPage)
            ->get()
            ->map(fn($row) => $this->toArray($row))
            ->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: 1,
        );
    }

    public function getSummary(string $auditableType, int $auditableId): array
    {
        $baseQuery = fn() => DB::table(self::TABLE)
            ->where('auditable_type', $auditableType)
            ->where('auditable_id', $auditableId);

        $totalChanges = $baseQuery()->count();

        $eventCounts = $baseQuery()
            ->selectRaw('event, count(*) as count')
            ->groupBy('event')
            ->pluck('count', 'event')
            ->toArray();

        $uniqueUsers = $baseQuery()
            ->distinct()
            ->count('user_id');

        $lastChange = $baseQuery()
            ->orderBy('created_at', 'desc')
            ->first();

        $firstChange = $baseQuery()
            ->orderBy('created_at', 'asc')
            ->first();

        $lastUser = null;
        if ($lastChange && $lastChange->user_id) {
            $lastUser = DB::table('users')
                ->select('id', 'name', 'email')
                ->where('id', $lastChange->user_id)
                ->first();
        }

        return [
            'total_changes' => $totalChanges,
            'event_counts' => $eventCounts,
            'unique_users' => $uniqueUsers,
            'first_change_at' => $firstChange?->created_at,
            'last_change_at' => $lastChange?->created_at,
            'last_change_by' => $lastUser ? (array) $lastUser : null,
        ];
    }

    public function create(array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        return $this->findById($id);
    }

    /**
     * Convert row to array with computed fields.
     */
    private function toArray(object $row): array
    {
        $result = (array) $row;

        // Decode JSON fields
        if (isset($result['old_values']) && is_string($result['old_values'])) {
            $result['old_values'] = json_decode($result['old_values'], true);
        }
        if (isset($result['new_values']) && is_string($result['new_values'])) {
            $result['new_values'] = json_decode($result['new_values'], true);
        }
        if (isset($result['tags']) && is_string($result['tags'])) {
            $result['tags'] = json_decode($result['tags'], true);
        }

        // Compute changed fields
        $oldValues = $result['old_values'] ?? [];
        $newValues = $result['new_values'] ?? [];
        $result['changed_fields'] = array_unique(
            array_merge(array_keys($oldValues), array_keys($newValues))
        );

        return $result;
    }

    /**
     * Convert row to array with user relation.
     */
    private function toArrayWithUser(object $row): array
    {
        $result = $this->toArray($row);

        if ($row->user_id) {
            $user = DB::table('users')
                ->select('id', 'name', 'email')
                ->where('id', $row->user_id)
                ->first();
            $result['user'] = $user ? (array) $user : null;
        } else {
            $result['user'] = null;
        }

        return $result;
    }
}
