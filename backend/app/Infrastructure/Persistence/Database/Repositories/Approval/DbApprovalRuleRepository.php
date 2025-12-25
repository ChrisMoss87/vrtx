<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Approval;

use App\Domain\Approval\Entities\ApprovalRule;
use App\Domain\Approval\Repositories\ApprovalRuleRepositoryInterface;
use App\Domain\Approval\ValueObjects\ApprovalType;
use App\Domain\Approval\ValueObjects\EntityType;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbApprovalRuleRepository implements ApprovalRuleRepositoryInterface
{
    private const TABLE = 'approval_rules';

    public function findById(int $id): ?ApprovalRule
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByModuleId(int $moduleId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findActiveByModuleId(int $moduleId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function save(ApprovalRule $rule): ApprovalRule
    {
        $data = $this->toRowData($rule);

        if ($rule->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $rule->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $rule->getId();
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
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function findMatchingRule(string $entityType, array $data): ?ApprovalRule
    {
        $rows = DB::table(self::TABLE)
            ->where('is_active', true)
            ->where('entity_type', $entityType)
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();

        foreach ($rows as $row) {
            $rule = $this->toDomainEntity($row);
            if ($rule->matchesConditions($data)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Convert a database row to a domain entity.
     */
    private function toDomainEntity(stdClass $row): ApprovalRule
    {
        return ApprovalRule::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            description: $row->description,
            entityType: EntityType::from($row->entity_type),
            moduleId: $row->module_id ? (int) $row->module_id : null,
            conditions: $row->conditions ? (is_string($row->conditions) ? json_decode($row->conditions, true) : $row->conditions) : [],
            approverChain: $row->approver_chain ? (is_string($row->approver_chain) ? json_decode($row->approver_chain, true) : $row->approver_chain) : [],
            approvalType: ApprovalType::from($row->approval_type),
            allowSelfApproval: (bool) $row->allow_self_approval,
            requireComments: (bool) $row->require_comments,
            slaHours: $row->sla_hours ? (int) $row->sla_hours : null,
            escalationRules: $row->escalation_rules ? (is_string($row->escalation_rules) ? json_decode($row->escalation_rules, true) : $row->escalation_rules) : [],
            notificationSettings: $row->notification_settings ? (is_string($row->notification_settings) ? json_decode($row->notification_settings, true) : $row->notification_settings) : [],
            isActive: (bool) $row->is_active,
            priority: (int) ($row->priority ?? 0),
            createdBy: $row->created_by ? (int) $row->created_by : null,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * Convert a domain entity to row data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(ApprovalRule $rule): array
    {
        return [
            'name' => $rule->getName(),
            'description' => $rule->getDescription(),
            'entity_type' => $rule->getEntityType()->value,
            'module_id' => $rule->getModuleId(),
            'conditions' => json_encode($rule->getConditions()),
            'approver_chain' => json_encode($rule->getApproverChain()),
            'approval_type' => $rule->getApprovalType()->value,
            'allow_self_approval' => $rule->allowsSelfApproval(),
            'require_comments' => $rule->requiresComments(),
            'sla_hours' => $rule->getSlaHours(),
            'escalation_rules' => json_encode($rule->getEscalationRules()),
            'notification_settings' => json_encode($rule->getNotificationSettings()),
            'is_active' => $rule->isActive(),
            'priority' => $rule->getPriority(),
            'created_by' => $rule->getCreatedBy(),
        ];
    }
}
