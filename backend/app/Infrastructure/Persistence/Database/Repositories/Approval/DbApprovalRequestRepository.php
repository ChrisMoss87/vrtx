<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Approval;

use App\Domain\Approval\Entities\ApprovalRequest as ApprovalRequestEntity;
use App\Domain\Approval\Entities\ApprovalStep as ApprovalStepEntity;
use App\Domain\Approval\Repositories\ApprovalRequestRepositoryInterface;
use App\Domain\Approval\ValueObjects\ApprovalStatus;
use App\Domain\Approval\ValueObjects\ApprovalType;
use App\Domain\Approval\ValueObjects\StepStatus;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DbApprovalRequestRepository implements ApprovalRequestRepositoryInterface
{
    private const TABLE = 'approval_requests';
    private const TABLE_STEPS = 'approval_steps';
    private const TABLE_RULES = 'approval_rules';
    private const TABLE_USERS = 'users';

    private const STATUS_PENDING = 'pending';
    private const STEP_STATUS_PENDING = 'pending';

    // =========================================================================
    // ENTITY-BASED METHODS (for domain logic)
    // =========================================================================
    public function findById(int $id): ?ApprovalRequestEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findByUuid(string $uuid): ?ApprovalRequestEntity
    {
        $row = DB::table(self::TABLE)->where('uuid', $uuid)->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function save(ApprovalRequestEntity $entity): ApprovalRequestEntity
    {
        $data = [
            'rule_id' => $entity->getRuleId(),
            'entity_type' => 'module_record',
            'entity_id' => $entity->getRecordId(),
            'status' => $entity->getStatus()->value,
            'requested_by' => $entity->getRequestedBy(),
        ];

        if ($entity->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $entity->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $entity->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    // =========================================================================
    // ARRAY-BASED QUERY METHODS (for application layer)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $row ? $this->rowToArrayWithRelations($row) : null;
    }

    public function findByUuidAsArray(string $uuid): ?array
    {
        $row = DB::table(self::TABLE)->where('uuid', $uuid)->first();
        return $row ? $this->rowToArrayWithRelations($row) : null;
    }

    public function findByRecordId(int $moduleId, int $recordId): array
    {
        // Get rule IDs for this module
        $ruleIds = DB::table(self::TABLE_RULES)
            ->where('module_id', $moduleId)
            ->pluck('id');

        $rows = DB::table(self::TABLE)
            ->where('entity_type', 'module_record')
            ->where('entity_id', $recordId)
            ->whereIn('rule_id', $ruleIds)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();
    }

    public function findPendingForApprover(int $approverId): array
    {
        // Get request IDs where this user is the current approver
        $requestIds = DB::table(self::TABLE_STEPS)
            ->where('approver_id', $approverId)
            ->where('is_current', true)
            ->where('status', self::STEP_STATUS_PENDING)
            ->pluck('request_id');

        $rows = DB::table(self::TABLE)
            ->whereIn('id', $requestIds)
            ->where('status', self::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();
    }

    public function findPendingForRecord(int $moduleId, int $recordId): ?array
    {
        // Get rule IDs for this module
        $ruleIds = DB::table(self::TABLE_RULES)
            ->where('module_id', $moduleId)
            ->pluck('id');

        $row = DB::table(self::TABLE)
            ->where('status', self::STATUS_PENDING)
            ->where('entity_type', 'module_record')
            ->where('entity_id', $recordId)
            ->whereIn('rule_id', $ruleIds)
            ->first();

        return $row ? $this->rowToArrayWithRelations($row) : null;
    }

    public function findByEntityType(string $entityType, ?string $status = null): array
    {
        $query = DB::table(self::TABLE)->where('entity_type', $entityType);

        if ($status !== null) {
            $query->where('status', $status);
        }

        $rows = $query->orderByDesc('created_at')->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();
    }

    public function findWithFilters(array $filters, int $perPage = 25): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        // Filter by status
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        // Filter by module
        if (!empty($filters['module_id'])) {
            $ruleIds = DB::table(self::TABLE_RULES)
                ->where('module_id', $filters['module_id'])
                ->pluck('id');
            $query->whereIn('rule_id', $ruleIds);
        }

        // Filter by approver
        if (!empty($filters['approver_id'])) {
            $requestIds = DB::table(self::TABLE_STEPS)
                ->where('approver_id', $filters['approver_id'])
                ->pluck('request_id');
            $query->whereIn('id', $requestIds);
        }

        // Filter by requester
        if (!empty($filters['requested_by'])) {
            $query->where('requested_by', $filters['requested_by']);
        }

        // Filter by entity type
        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        // Filter by entity id
        if (!empty($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }

        // Filter by date range
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        // Search in title/description
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        $currentPage = $filters['page'] ?? 1;
        $total = $query->count();

        $rows = $query
            ->offset(($currentPage - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
        );
    }

    public function findByApprover(int $approverId, array $filters = []): array
    {
        $requestIds = DB::table(self::TABLE_STEPS)
            ->where('approver_id', $approverId)
            ->pluck('request_id');

        $query = DB::table(self::TABLE)->whereIn('id', $requestIds);

        // Apply status filter
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        // Apply date range filter
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        $rows = $query->orderByDesc('created_at')->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();
    }

    public function findByRequester(int $requesterId, array $filters = []): array
    {
        $query = DB::table(self::TABLE)->where('requested_by', $requesterId);

        // Apply status filter
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        // Apply date range filter
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        $rows = $query->orderByDesc('created_at')->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();
    }

    public function findByModuleId(int $moduleId, array $filters = []): array
    {
        $ruleIds = DB::table(self::TABLE_RULES)
            ->where('module_id', $moduleId)
            ->pluck('id');

        $query = DB::table(self::TABLE)->whereIn('rule_id', $ruleIds);

        // Apply status filter
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        // Apply date range filter
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        $rows = $query->orderByDesc('created_at')->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();
    }

    // =========================================================================
    // MUTATION & UTILITY METHODS
    // =========================================================================

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function countPendingForApprover(int $approverId): int
    {
        $requestIds = DB::table(self::TABLE_STEPS)
            ->where('approver_id', $approverId)
            ->where('is_current', true)
            ->where('status', self::STEP_STATUS_PENDING)
            ->pluck('request_id');

        return DB::table(self::TABLE)
            ->whereIn('id', $requestIds)
            ->where('status', self::STATUS_PENDING)
            ->count();
    }

    public function countByStatus(string $status, ?int $approverId = null): int
    {
        $query = DB::table(self::TABLE)->where('status', $status);

        if ($approverId !== null) {
            $requestIds = DB::table(self::TABLE_STEPS)
                ->where('approver_id', $approverId)
                ->pluck('request_id');
            $query->whereIn('id', $requestIds);
        }

        return $query->count();
    }

    public function getStatsByApprover(int $approverId): array
    {
        $requestIds = DB::table(self::TABLE_STEPS)
            ->where('approver_id', $approverId)
            ->pluck('request_id');

        $results = DB::table(self::TABLE)
            ->select('status', DB::raw('count(*) as count'))
            ->whereIn('id', $requestIds)
            ->groupBy('status')
            ->get();

        $stats = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'cancelled' => 0,
            'expired' => 0,
        ];

        foreach ($results as $row) {
            $stats['total'] += $row->count;
            $stats[$row->status] = $row->count;
        }

        return $stats;
    }

    public function getStatsByModule(int $moduleId): array
    {
        $ruleIds = DB::table(self::TABLE_RULES)
            ->where('module_id', $moduleId)
            ->pluck('id');

        $results = DB::table(self::TABLE)
            ->select('status', DB::raw('count(*) as count'))
            ->whereIn('rule_id', $ruleIds)
            ->groupBy('status')
            ->get();

        $stats = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'cancelled' => 0,
            'expired' => 0,
        ];

        foreach ($results as $row) {
            $stats['total'] += $row->count;
            $stats[$row->status] = $row->count;
        }

        return $stats;
    }

    // =========================================================================
    // PRIVATE HELPER METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): ApprovalRequestEntity
    {
        $steps = $this->getStepsForRequest((int) $row->id);
        $rule = DB::table(self::TABLE_RULES)->where('id', $row->rule_id)->first();

        $approverIds = collect($steps)->pluck('approver_id')->filter()->values()->all();
        $currentStep = collect($steps)->where('is_current', true)->first();

        $entity = ApprovalRequestEntity::reconstitute(
            id: (int) $row->id,
            moduleId: $rule?->module_id ? (int) $rule->module_id : 0,
            recordId: (int) $row->entity_id,
            ruleId: $row->rule_id ? (int) $row->rule_id : null,
            type: ApprovalType::from($rule?->approval_type ?? 'sequential'),
            status: ApprovalStatus::from($row->status),
            requestedBy: $row->requested_by ? (int) $row->requested_by : null,
            requestReason: $row->description,
            approverIds: $approverIds,
            currentStep: $currentStep['step_order'] ?? 1,
            totalSteps: count($steps),
            dueAt: $row->expires_at ? new DateTimeImmutable($row->expires_at) : null,
            completedAt: $row->completed_at ? new DateTimeImmutable($row->completed_at) : null,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );

        // Load steps as domain entities
        $stepEntities = [];
        foreach ($steps as $step) {
            $stepEntities[] = $this->stepToDomainEntity($step);
        }
        $entity->setSteps($stepEntities);

        return $entity;
    }

    private function stepToDomainEntity(array $step): ApprovalStepEntity
    {
        return ApprovalStepEntity::reconstitute(
            id: (int) $step['id'],
            requestId: (int) $step['request_id'],
            approverId: $step['approver_id'] ? (int) $step['approver_id'] : null,
            roleId: $step['role_id'] ? (int) $step['role_id'] : null,
            approverType: $step['approver_type'] ?? 'user',
            stepOrder: (int) $step['step_order'],
            status: StepStatus::from($step['status']),
            isCurrent: (bool) $step['is_current'],
            comments: $step['comments'],
            respondedAt: $step['responded_at'] ? new DateTimeImmutable($step['responded_at']) : null,
            dueAt: $step['due_at'] ? new DateTimeImmutable($step['due_at']) : null,
            delegatedToId: $step['delegated_to_id'] ? (int) $step['delegated_to_id'] : null,
            delegatedById: $step['delegated_by_id'] ? (int) $step['delegated_by_id'] : null,
            createdAt: new DateTimeImmutable($step['created_at']),
            updatedAt: $step['updated_at'] ? new DateTimeImmutable($step['updated_at']) : null,
        );
    }

    private function getStepsForRequest(int $requestId): array
    {
        $rows = DB::table(self::TABLE_STEPS)
            ->where('request_id', $requestId)
            ->orderBy('step_order')
            ->get();

        return $rows->map(fn($row) => (array) $row)->all();
    }

    private function rowToArrayWithRelations(stdClass $row): array
    {
        $data = (array) $row;

        // Load steps
        $steps = DB::table(self::TABLE_STEPS)
            ->where('request_id', $row->id)
            ->orderBy('step_order')
            ->get();

        $data['steps'] = $steps->map(function ($step) {
            $stepData = (array) $step;

            // Load approver
            if ($step->approver_id) {
                $approver = DB::table(self::TABLE_USERS)
                    ->select('id', 'name', 'email')
                    ->where('id', $step->approver_id)
                    ->first();
                $stepData['approver'] = $approver ? (array) $approver : null;
            }

            return $stepData;
        })->all();

        // Load rule
        if ($row->rule_id) {
            $rule = DB::table(self::TABLE_RULES)->where('id', $row->rule_id)->first();
            $data['rule'] = $rule ? (array) $rule : null;
        }

        return $data;
    }
}
