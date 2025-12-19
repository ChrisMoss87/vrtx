<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Approval;

use App\Domain\Approval\Entities\ApprovalRequest as ApprovalRequestEntity;
use App\Domain\Approval\Entities\ApprovalStep as ApprovalStepEntity;
use App\Domain\Approval\Repositories\ApprovalRequestRepositoryInterface;
use App\Domain\Approval\ValueObjects\ApprovalStatus;
use App\Domain\Approval\ValueObjects\ApprovalType;
use App\Domain\Approval\ValueObjects\StepStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use DateTimeImmutable;

final class EloquentApprovalRequestRepository implements ApprovalRequestRepositoryInterface
{
    public function findById(int $id): ?ApprovalRequestEntity
    {
        $model = ApprovalRequest::with(['steps', 'rule'])->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByUuid(string $uuid): ?ApprovalRequestEntity
    {
        $model = ApprovalRequest::with(['steps', 'rule'])
            ->where('uuid', $uuid)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findByRecordId(int $moduleId, int $recordId): array
    {
        $models = ApprovalRequest::with(['steps', 'rule'])
            ->where('entity_type', 'module_record')
            ->where('entity_id', $recordId)
            ->whereHas('rule', fn ($q) => $q->where('module_id', $moduleId))
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn ($model) => $this->toDomain($model))->all();
    }

    public function findPendingForApprover(int $approverId): array
    {
        $models = ApprovalRequest::with(['steps', 'rule'])
            ->pending()
            ->whereHas('steps', function ($q) use ($approverId) {
                $q->where('approver_id', $approverId)
                    ->where('is_current', true)
                    ->where('status', ApprovalStep::STATUS_PENDING);
            })
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn ($model) => $this->toDomain($model))->all();
    }

    public function findPendingForRecord(int $moduleId, int $recordId): ?ApprovalRequestEntity
    {
        $model = ApprovalRequest::with(['steps', 'rule'])
            ->pending()
            ->where('entity_type', 'module_record')
            ->where('entity_id', $recordId)
            ->whereHas('rule', fn ($q) => $q->where('module_id', $moduleId))
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findByEntityType(string $entityType, ?string $status = null): array
    {
        $query = ApprovalRequest::with(['steps', 'rule'])
            ->where('entity_type', $entityType);

        if ($status !== null) {
            $query->where('status', $status);
        }

        $models = $query->orderByDesc('created_at')->get();

        return $models->map(fn ($model) => $this->toDomain($model))->all();
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
            $model = ApprovalRequest::findOrFail($entity->getId());
            $model->update($data);
        } else {
            $model = ApprovalRequest::create($data);
        }

        return $this->toDomain($model->fresh(['steps', 'rule']));
    }

    public function delete(int $id): bool
    {
        return (bool) ApprovalRequest::destroy($id);
    }

    public function countPendingForApprover(int $approverId): int
    {
        return ApprovalRequest::pending()
            ->whereHas('steps', function ($q) use ($approverId) {
                $q->where('approver_id', $approverId)
                    ->where('is_current', true)
                    ->where('status', ApprovalStep::STATUS_PENDING);
            })
            ->count();
    }

    private function toDomain(ApprovalRequest $model): ApprovalRequestEntity
    {
        $approverIds = $model->steps->pluck('approver_id')->filter()->values()->all();
        $currentStep = $model->steps->where('is_current', true)->first();

        $entity = ApprovalRequestEntity::reconstitute(
            id: $model->id,
            moduleId: $model->rule?->module_id ?? 0,
            recordId: $model->entity_id,
            ruleId: $model->rule_id,
            type: ApprovalType::from($model->rule?->approval_type ?? 'sequential'),
            status: ApprovalStatus::from($model->status),
            requestedBy: $model->requested_by,
            requestReason: $model->description,
            approverIds: $approverIds,
            currentStep: $currentStep?->step_order ?? 1,
            totalSteps: $model->steps->count(),
            dueAt: $model->expires_at ? new DateTimeImmutable($model->expires_at->toDateTimeString()) : null,
            completedAt: $model->completed_at ? new DateTimeImmutable($model->completed_at->toDateTimeString()) : null,
            createdAt: new DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at->toDateTimeString()) : null,
        );

        // Load steps
        if ($model->relationLoaded('steps')) {
            $steps = [];
            foreach ($model->steps as $stepModel) {
                $steps[] = $this->stepToDomain($stepModel);
            }
            $entity->setSteps($steps);
        }

        return $entity;
    }

    private function stepToDomain(ApprovalStep $model): ApprovalStepEntity
    {
        return ApprovalStepEntity::reconstitute(
            id: $model->id,
            requestId: $model->request_id,
            approverId: $model->approver_id,
            roleId: $model->role_id,
            approverType: $model->approver_type ?? 'user',
            stepOrder: $model->step_order,
            status: StepStatus::from($model->status),
            isCurrent: $model->is_current,
            comments: $model->comments,
            respondedAt: $model->responded_at ? new DateTimeImmutable($model->responded_at->toDateTimeString()) : null,
            dueAt: $model->due_at ? new DateTimeImmutable($model->due_at->toDateTimeString()) : null,
            delegatedToId: $model->delegated_to_id,
            delegatedById: $model->delegated_by_id,
            createdAt: new DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: $model->updated_at ? new DateTimeImmutable($model->updated_at->toDateTimeString()) : null,
        );
    }
}
