<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Approval;

use App\Domain\Approval\Entities\ApprovalRule;
use App\Domain\Approval\Repositories\ApprovalRuleRepositoryInterface;
use App\Domain\Approval\ValueObjects\ApprovalType;
use App\Domain\Approval\ValueObjects\EntityType;
use App\Models\ApprovalRule as ApprovalRuleModel;
use DateTimeImmutable;

class EloquentApprovalRuleRepository implements ApprovalRuleRepositoryInterface
{
    public function findById(int $id): ?ApprovalRule
    {
        $model = ApprovalRuleModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByModuleId(int $moduleId): array
    {
        $models = ApprovalRuleModel::where('module_id', $moduleId)
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findActiveByModuleId(int $moduleId): array
    {
        $models = ApprovalRuleModel::where('module_id', $moduleId)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(ApprovalRule $rule): ApprovalRule
    {
        $data = $this->toModelData($rule);

        if ($rule->getId() !== null) {
            $model = ApprovalRuleModel::findOrFail($rule->getId());
            $model->update($data);
        } else {
            $model = ApprovalRuleModel::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    public function delete(int $id): bool
    {
        $model = ApprovalRuleModel::find($id);

        if (!$model) {
            return false;
        }

        return $model->delete() ?? false;
    }

    /**
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(ApprovalRuleModel $model): ApprovalRule
    {
        return ApprovalRule::reconstitute(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            entityType: EntityType::from($model->entity_type),
            moduleId: $model->module_id,
            conditions: $model->conditions ?? [],
            approverChain: $model->approver_chain ?? [],
            approvalType: ApprovalType::from($model->approval_type),
            allowSelfApproval: $model->allow_self_approval,
            requireComments: $model->require_comments,
            slaHours: $model->sla_hours,
            escalationRules: $model->escalation_rules ?? [],
            notificationSettings: $model->notification_settings ?? [],
            isActive: $model->is_active,
            priority: $model->priority ?? 0,
            createdBy: $model->created_by,
            createdAt: $model->created_at
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
            updatedAt: $model->updated_at
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
        );
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(ApprovalRule $rule): array
    {
        return [
            'name' => $rule->getName(),
            'description' => $rule->getDescription(),
            'entity_type' => $rule->getEntityType()->value,
            'module_id' => $rule->getModuleId(),
            'conditions' => $rule->getConditions(),
            'approver_chain' => $rule->getApproverChain(),
            'approval_type' => $rule->getApprovalType()->value,
            'allow_self_approval' => $rule->allowsSelfApproval(),
            'require_comments' => $rule->requiresComments(),
            'sla_hours' => $rule->getSlaHours(),
            'escalation_rules' => $rule->getEscalationRules(),
            'notification_settings' => $rule->getNotificationSettings(),
            'is_active' => $rule->isActive(),
            'priority' => $rule->getPriority(),
            'created_by' => $rule->getCreatedBy(),
        ];
    }
}
