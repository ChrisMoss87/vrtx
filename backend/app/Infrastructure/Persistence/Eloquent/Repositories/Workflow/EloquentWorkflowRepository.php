<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Workflow;

use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Workflow\Entities\Workflow;
use App\Domain\Workflow\Entities\WorkflowStep;
use App\Domain\Workflow\Repositories\WorkflowRepositoryInterface;
use App\Domain\Workflow\ValueObjects\ActionConfig;
use App\Domain\Workflow\ValueObjects\ActionType;
use App\Domain\Workflow\ValueObjects\StepConditions;
use App\Domain\Workflow\ValueObjects\TriggerConfig;
use App\Domain\Workflow\ValueObjects\TriggerTiming;
use App\Domain\Workflow\ValueObjects\TriggerType;
use App\Models\Workflow as WorkflowModel;
use App\Models\WorkflowRunHistory;
use App\Models\WorkflowStep as WorkflowStepModel;
use DateTimeImmutable;

/**
 * Eloquent implementation of the WorkflowRepository.
 */
class EloquentWorkflowRepository implements WorkflowRepositoryInterface
{
    public function findById(int $id): ?Workflow
    {
        $model = WorkflowModel::with('steps')->find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findAll(): array
    {
        $models = WorkflowModel::with('steps')
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findActiveForModule(int $moduleId): array
    {
        $models = WorkflowModel::with('steps')
            ->where('module_id', $moduleId)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByTriggerType(int $moduleId, TriggerType $triggerType): array
    {
        $models = WorkflowModel::with('steps')
            ->where('module_id', $moduleId)
            ->where('is_active', true)
            ->where('trigger_type', $triggerType->value)
            ->orderBy('priority', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findScheduledForExecution(): array
    {
        $models = WorkflowModel::with('steps')
            ->where('is_active', true)
            ->where('trigger_type', TriggerType::TIME_BASED->value)
            ->where(function ($query) {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->orderBy('priority', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(Workflow $workflow): Workflow
    {
        $data = $this->toModelData($workflow);

        if ($workflow->getId() !== null) {
            $model = WorkflowModel::findOrFail($workflow->getId());
            $model->update($data);
        } else {
            $model = WorkflowModel::create($data);
        }

        // Save steps if present
        $steps = $workflow->steps();
        if (!empty($steps)) {
            $this->saveSteps($model, $steps);
        }

        return $this->toDomainEntity($model->fresh(['steps']));
    }

    public function delete(int $id): bool
    {
        $model = WorkflowModel::find($id);

        if (!$model) {
            return false;
        }

        // Delete steps first
        $model->steps()->delete();

        return $model->delete() ?? false;
    }

    public function hasRunForRecord(int $workflowId, int $recordId, string $recordType, ?string $triggerType = null): bool
    {
        $query = WorkflowRunHistory::where('workflow_id', $workflowId)
            ->where('record_id', $recordId)
            ->where('record_type', $recordType);

        if ($triggerType !== null) {
            $query->where('trigger_type', $triggerType);
        }

        return $query->exists();
    }

    public function recordRunForRecord(int $workflowId, int $recordId, string $recordType, string $triggerType): void
    {
        WorkflowRunHistory::create([
            'workflow_id' => $workflowId,
            'record_id' => $recordId,
            'record_type' => $recordType,
            'trigger_type' => $triggerType,
            'executed_at' => now(),
        ]);
    }

    /**
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(WorkflowModel $model): Workflow
    {
        $workflow = Workflow::reconstitute(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            moduleId: $model->module_id,
            isActive: $model->is_active,
            priority: $model->priority ?? 0,
            triggerType: TriggerType::from($model->trigger_type),
            triggerConfig: TriggerConfig::fromArray($model->trigger_config ?? []),
            triggerTiming: TriggerTiming::tryFrom($model->trigger_timing ?? 'all') ?? TriggerTiming::ALL,
            watchedFields: $model->watched_fields ?? [],
            webhookSecret: $model->webhook_secret,
            stopOnFirstMatch: $model->stop_on_first_match ?? false,
            maxExecutionsPerDay: $model->max_executions_per_day,
            executionsToday: $model->executions_today ?? 0,
            executionsTodayDate: $model->executions_today_date
                ? new DateTimeImmutable($model->executions_today_date->toDateString())
                : null,
            conditions: $model->conditions ?? [],
            runOncePerRecord: $model->run_once_per_record ?? false,
            allowManualTrigger: $model->allow_manual_trigger ?? true,
            delaySeconds: $model->delay_seconds ?? 0,
            scheduleCron: $model->schedule_cron,
            lastRunAt: $model->last_run_at
                ? Timestamp::fromDateTime($model->last_run_at)
                : null,
            nextRunAt: $model->next_run_at
                ? Timestamp::fromDateTime($model->next_run_at)
                : null,
            executionCount: $model->execution_count ?? 0,
            successCount: $model->success_count ?? 0,
            failureCount: $model->failure_count ?? 0,
            createdBy: $model->created_by ? UserId::fromInt($model->created_by) : null,
            updatedBy: $model->updated_by ? UserId::fromInt($model->updated_by) : null,
            createdAt: $model->created_at
                ? Timestamp::fromDateTime($model->created_at)
                : null,
            updatedAt: $model->updated_at
                ? Timestamp::fromDateTime($model->updated_at)
                : null,
        );

        // Add steps to workflow
        if ($model->relationLoaded('steps')) {
            $steps = $model->steps->map(fn($s) => $this->stepToDomainEntity($s))->all();
            $workflow->setSteps($steps);
        }

        return $workflow;
    }

    /**
     * Convert a WorkflowStep model to domain entity.
     */
    private function stepToDomainEntity(WorkflowStepModel $model): WorkflowStep
    {
        return WorkflowStep::reconstitute(
            id: $model->id,
            workflowId: $model->workflow_id,
            order: $model->order ?? 0,
            name: $model->name,
            actionType: ActionType::from($model->action_type),
            actionConfig: ActionConfig::fromArray($model->action_config ?? []),
            conditions: StepConditions::fromArray($model->conditions ?? []),
            branchId: $model->branch_id,
            isParallel: $model->is_parallel ?? false,
            continueOnError: $model->continue_on_error ?? false,
            retryCount: $model->retry_count ?? 0,
            retryDelaySeconds: $model->retry_delay_seconds ?? 60,
        );
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(Workflow $workflow): array
    {
        return [
            'name' => $workflow->name(),
            'description' => $workflow->description(),
            'module_id' => $workflow->moduleId(),
            'is_active' => $workflow->isActive(),
            'priority' => $workflow->priority(),
            'trigger_type' => $workflow->triggerType()->value,
            'trigger_config' => $workflow->triggerConfig()->toArray(),
            'trigger_timing' => $workflow->triggerTiming()->value,
            'watched_fields' => $workflow->watchedFields(),
            'webhook_secret' => $workflow->webhookSecret(),
            'stop_on_first_match' => $workflow->stopOnFirstMatch(),
            'max_executions_per_day' => $workflow->maxExecutionsPerDay(),
            'executions_today' => $workflow->executionsToday(),
            'executions_today_date' => $workflow->executionsTodayDate()?->format('Y-m-d'),
            'conditions' => $workflow->conditions(),
            'run_once_per_record' => $workflow->runOncePerRecord(),
            'allow_manual_trigger' => $workflow->allowManualTrigger(),
            'delay_seconds' => $workflow->delaySeconds(),
            'schedule_cron' => $workflow->scheduleCron(),
            'last_run_at' => $workflow->lastRunAt()?->toDateTimeString(),
            'next_run_at' => $workflow->nextRunAt()?->toDateTimeString(),
            'execution_count' => $workflow->executionCount(),
            'success_count' => $workflow->successCount(),
            'failure_count' => $workflow->failureCount(),
            'created_by' => $workflow->createdBy()?->value(),
            'updated_by' => $workflow->updatedBy()?->value(),
        ];
    }

    /**
     * Save steps for a workflow.
     *
     * @param array<WorkflowStep> $steps
     */
    private function saveSteps(WorkflowModel $workflowModel, array $steps): void
    {
        // Delete existing steps
        $workflowModel->steps()->delete();

        // Create new steps
        foreach ($steps as $step) {
            WorkflowStepModel::create([
                'workflow_id' => $workflowModel->id,
                'order' => $step->order(),
                'name' => $step->name(),
                'action_type' => $step->actionType()->value,
                'action_config' => $step->actionConfig()->toArray(),
                'conditions' => $step->conditions()->toArray(),
                'branch_id' => $step->branchId(),
                'is_parallel' => $step->isParallel(),
                'continue_on_error' => $step->continueOnError(),
                'retry_count' => $step->retryCount(),
                'retry_delay_seconds' => $step->retryDelaySeconds(),
            ]);
        }
    }
}
