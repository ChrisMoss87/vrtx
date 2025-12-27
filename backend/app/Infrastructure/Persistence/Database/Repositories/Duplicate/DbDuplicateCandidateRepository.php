<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Duplicate;

use App\Domain\Duplicate\Entities\DuplicateCandidate as DuplicateCandidateEntity;
use App\Domain\Duplicate\Repositories\DuplicateCandidateRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbDuplicateCandidateRepository implements DuplicateCandidateRepositoryInterface
{
    private const TABLE = 'duplicate_candidates';
    private const TABLE_MODULES = 'modules';
    private const TABLE_MODULE_RECORDS = 'module_records';
    private const TABLE_USERS = 'users';

    private const STATUS_PENDING = 'pending';
    private const STATUS_MERGED = 'merged';
    private const STATUS_DISMISSED = 'dismissed';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?DuplicateCandidateEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(DuplicateCandidateEntity $candidate): DuplicateCandidateEntity
    {
        $data = $this->toRowData($candidate);

        if ($candidate->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $candidate->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $candidate->getId();
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

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->rowToArrayWithRelations($row);
    }

    public function findAll(): array
    {
        $rows = DB::table(self::TABLE)->get();

        return $rows->map(fn($row) => $this->rowToArray($row))->toArray();
    }

    public function listCandidates(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        // Filter by module
        if (!empty($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            // Default to pending only
            $query->where('status', self::STATUS_PENDING);
        }

        // Filter by minimum score
        if (!empty($filters['min_score'])) {
            $query->where('match_score', '>=', $filters['min_score']);
        }

        // Filter by record ID
        if (!empty($filters['record_id'])) {
            $recordId = $filters['record_id'];
            $query->where(function ($q) use ($recordId) {
                $q->where('record_id_a', $recordId)
                    ->orWhere('record_id_b', $recordId);
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'match_score';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Get total count
        $total = $query->count();

        // Get paginated results
        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->toArray();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getCandidatesForRecord(int $recordId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('status', self::STATUS_PENDING)
            ->where(function ($q) use ($recordId) {
                $q->where('record_id_a', $recordId)
                    ->orWhere('record_id_b', $recordId);
            })
            ->orderByDesc('match_score')
            ->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->toArray();
    }

    public function countByStatus(int $moduleId, string $status): int
    {
        return DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('status', $status)
            ->count();
    }

    public function countPendingForModule(int $moduleId): int
    {
        return DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('status', self::STATUS_PENDING)
            ->count();
    }

    public function countHighConfidence(int $moduleId, float $threshold = 0.9): int
    {
        return DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('status', self::STATUS_PENDING)
            ->where('match_score', '>=', $threshold)
            ->count();
    }

    public function getAverageScore(int $moduleId, ?string $status = null): float
    {
        $query = DB::table(self::TABLE)->where('module_id', $moduleId);

        if ($status) {
            $query->where('status', $status);
        } else {
            $query->where('status', self::STATUS_PENDING);
        }

        return (float) ($query->avg('match_score') ?? 0);
    }

    public function countByModule(): array
    {
        $results = DB::table(self::TABLE)
            ->select('module_id', DB::raw('COUNT(*) as count'))
            ->where('status', self::STATUS_PENDING)
            ->groupBy('module_id')
            ->get();

        return $results->map(function ($row) {
            $module = DB::table(self::TABLE_MODULES)
                ->select('id', 'name', 'api_name')
                ->where('id', $row->module_id)
                ->first();

            return [
                'module_id' => $row->module_id,
                'count' => $row->count,
                'module' => $module ? (array) $module : null,
            ];
        })->toArray();
    }

    public function exists(int $moduleId, int $recordIdA, int $recordIdB): ?array
    {
        return $this->findByRecordPair($moduleId, $recordIdA, $recordIdB);
    }

    public function findByRecordPair(int $moduleId, int $recordIdA, int $recordIdB): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('record_id_a', $recordIdA)
            ->where('record_id_b', $recordIdB)
            ->first();

        return $row ? $this->rowToArray($row) : null;
    }

    public function create(array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->rowToArray($row);
    }

    public function update(int $id, array $data): ?array
    {
        $exists = DB::table(self::TABLE)->where('id', $id)->exists();

        if (!$exists) {
            return null;
        }

        DB::table(self::TABLE)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->rowToArray($row);
    }

    public function markAsMerged(int $id, int $userId): bool
    {
        $exists = DB::table(self::TABLE)->where('id', $id)->exists();

        if (!$exists) {
            return false;
        }

        DB::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'status' => self::STATUS_MERGED,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        return true;
    }

    public function markAsDismissed(int $id, int $userId, ?string $reason = null): bool
    {
        $exists = DB::table(self::TABLE)->where('id', $id)->exists();

        if (!$exists) {
            return false;
        }

        $updateData = [
            'status' => self::STATUS_DISMISSED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ];

        if ($reason !== null) {
            $updateData['dismiss_reason'] = $reason;
        }

        DB::table(self::TABLE)
            ->where('id', $id)
            ->update($updateData);

        return true;
    }

    public function bulkUpdate(array $ids, array $data): int
    {
        return DB::table(self::TABLE)
            ->whereIn('id', $ids)
            ->update(array_merge($data, ['updated_at' => now()]));
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): DuplicateCandidateEntity
    {
        return DuplicateCandidateEntity::reconstitute(
            id: (int) $row->id,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(DuplicateCandidateEntity $candidate): array
    {
        // The entity is minimal, most data is passed directly via create/update methods
        return [];
    }

    /**
     * Convert a database row to array.
     */
    private function rowToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'module_id' => $row->module_id,
            'record_id_a' => $row->record_id_a,
            'record_id_b' => $row->record_id_b,
            'match_score' => $row->match_score,
            'matching_fields' => $row->matching_fields ? (is_string($row->matching_fields) ? json_decode($row->matching_fields, true) : $row->matching_fields) : [],
            'status' => $row->status,
            'reviewed_by' => $row->reviewed_by ?? null,
            'reviewed_at' => $row->reviewed_at ?? null,
            'dismiss_reason' => $row->dismiss_reason ?? null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Convert a database row to array with relations.
     */
    private function rowToArrayWithRelations(stdClass $row): array
    {
        $data = $this->rowToArray($row);

        // Load module relation
        if ($row->module_id) {
            $module = DB::table(self::TABLE_MODULES)
                ->select('id', 'name', 'api_name')
                ->where('id', $row->module_id)
                ->first();
            $data['module'] = $module ? (array) $module : null;
        }

        // Load record A relation
        if ($row->record_id_a) {
            $recordA = DB::table(self::TABLE_MODULE_RECORDS)
                ->where('id', $row->record_id_a)
                ->first();
            $data['record_a'] = $recordA ? $this->moduleRecordToArray($recordA) : null;
        }

        // Load record B relation
        if ($row->record_id_b) {
            $recordB = DB::table(self::TABLE_MODULE_RECORDS)
                ->where('id', $row->record_id_b)
                ->first();
            $data['record_b'] = $recordB ? $this->moduleRecordToArray($recordB) : null;
        }

        // Load reviewer relation
        if (!empty($row->reviewed_by)) {
            $reviewer = DB::table(self::TABLE_USERS)
                ->select('id', 'name')
                ->where('id', $row->reviewed_by)
                ->first();
            $data['reviewer'] = $reviewer ? (array) $reviewer : null;
        }

        return $data;
    }

    /**
     * Convert a module record row to array.
     */
    private function moduleRecordToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'module_id' => $row->module_id,
            'data' => $row->data ? (is_string($row->data) ? json_decode($row->data, true) : $row->data) : [],
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
