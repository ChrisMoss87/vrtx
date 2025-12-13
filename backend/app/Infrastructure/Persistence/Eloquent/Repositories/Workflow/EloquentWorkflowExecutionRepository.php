<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Workflow;

use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Workflow\Entities\WorkflowExecution;
use App\Domain\Workflow\Entities\WorkflowStepLog;
use App\Domain\Workflow\Repositories\WorkflowExecutionRepositoryInterface;
use App\Domain\Workflow\ValueObjects\ActionType;
use App\Domain\Workflow\ValueObjects\ExecutionStatus;
use App\Models\WorkflowExecution as ExecutionModel;
use App\Models\WorkflowStepLog as StepLogModel;
use DateTimeImmutable;

/**
 * Eloquent implementation of the WorkflowExecutionRepository.
 */
class EloquentWorkflowExecutionRepository implements WorkflowExecutionRepositoryInterface
{
    public function findById(int $id): ?WorkflowExecution
    {
        $model = ExecutionModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByWorkflowId(int $workflowId, int $limit = 50): array
    {
        $models = ExecutionModel::where('workflow_id', $workflowId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByStatus(ExecutionStatus $status, int $limit = 100): array
    {
        $models = ExecutionModel::where('status', $status->value)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findRecent(int $days = 7, int $limit = 100): array
    {
        $models = ExecutionModel::where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByRecord(int $recordId, string $recordType, int $limit = 50): array
    {
        $models = ExecutionModel::where('trigger_record_id', $recordId)
            ->where('trigger_record_type', $recordType)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(WorkflowExecution $execution): WorkflowExecution
    {
        $data = $this->toModelData($execution);

        if ($execution->getId() !== null) {
            $model = ExecutionModel::findOrFail($execution->getId());
            $model->update($data);
        } else {
            $model = ExecutionModel::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    public function delete(int $id): bool
    {
        $model = ExecutionModel::find($id);

        if (!$model) {
            return false;
        }

        // Delete step logs first
        $model->stepLogs()->delete();

        return $model->delete() ?? false;
    }

    public function saveStepLog(WorkflowStepLog $log): WorkflowStepLog
    {
        $data = [
            'execution_id' => $log->executionId(),
            'step_id' => $log->stepId(),
            'action_type' => $log->actionType()->value,
            'status' => $log->status(),
            'started_at' => $log->startedAt()?->toDateTimeString(),
            'completed_at' => $log->completedAt()?->toDateTimeString(),
            'duration_ms' => $log->durationMs(),
            'input_data' => $log->inputData(),
            'output_data' => $log->outputData(),
            'error_message' => $log->errorMessage(),
            'attempt_number' => $log->attemptNumber(),
        ];

        if ($log->getId() !== null) {
            $model = StepLogModel::findOrFail($log->getId());
            $model->update($data);
        } else {
            $model = StepLogModel::create($data);
        }

        return $this->stepLogToDomainEntity($model);
    }

    public function findStepLogs(int $executionId): array
    {
        $models = StepLogModel::where('execution_id', $executionId)
            ->orderBy('created_at')
            ->get();

        return $models->map(fn($m) => $this->stepLogToDomainEntity($m))->all();
    }

    public function getStatisticsForWorkflow(int $workflowId, ?DateTimeImmutable $since = null): array
    {
        $query = ExecutionModel::where('workflow_id', $workflowId);

        if ($since !== null) {
            $query->where('created_at', '>=', $since->format('Y-m-d H:i:s'));
        }

        $total = $query->count();
        $completed = (clone $query)->where('status', ExecutionStatus::COMPLETED->value)->count();
        $failed = (clone $query)->where('status', ExecutionStatus::FAILED->value)->count();
        $cancelled = (clone $query)->where('status', ExecutionStatus::CANCELLED->value)->count();

        $avgDuration = (clone $query)
            ->whereNotNull('duration_ms')
            ->avg('duration_ms') ?? 0;

        return [
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'cancelled' => $cancelled,
            'avg_duration_ms' => (float) $avgDuration,
        ];
    }

    public function cleanupOlderThan(DateTimeImmutable $cutoff): int
    {
        $cutoffString = $cutoff->format('Y-m-d H:i:s');

        // Delete step logs for old executions
        StepLogModel::whereHas('execution', function ($query) use ($cutoffString) {
            $query->where('created_at', '<', $cutoffString);
        })->delete();

        // Delete old executions
        return ExecutionModel::where('created_at', '<', $cutoffString)->delete();
    }

    /**
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(ExecutionModel $model): WorkflowExecution
    {
        return WorkflowExecution::reconstitute(
            id: $model->id,
            workflowId: $model->workflow_id,
            triggerType: $model->trigger_type,
            triggerRecordId: $model->trigger_record_id,
            triggerRecordType: $model->trigger_record_type,
            status: ExecutionStatus::from($model->status),
            queuedAt: $model->queued_at
                ? Timestamp::fromDateTime($model->queued_at)
                : null,
            startedAt: $model->started_at
                ? Timestamp::fromDateTime($model->started_at)
                : null,
            completedAt: $model->completed_at
                ? Timestamp::fromDateTime($model->completed_at)
                : null,
            durationMs: $model->duration_ms,
            contextData: $model->context_data ?? [],
            stepsCompleted: $model->steps_completed ?? 0,
            stepsFailed: $model->steps_failed ?? 0,
            stepsSkipped: $model->steps_skipped ?? 0,
            errorMessage: $model->error_message,
            triggeredBy: $model->triggered_by ? UserId::fromInt($model->triggered_by) : null,
            createdAt: $model->created_at
                ? Timestamp::fromDateTime($model->created_at)
                : null,
        );
    }

    /**
     * Convert a StepLog model to domain entity.
     */
    private function stepLogToDomainEntity(StepLogModel $model): WorkflowStepLog
    {
        return WorkflowStepLog::reconstitute(
            id: $model->id,
            executionId: $model->execution_id,
            stepId: $model->step_id,
            actionType: ActionType::from($model->action_type),
            status: $model->status,
            startedAt: $model->started_at
                ? Timestamp::fromDateTime($model->started_at)
                : null,
            completedAt: $model->completed_at
                ? Timestamp::fromDateTime($model->completed_at)
                : null,
            durationMs: $model->duration_ms,
            inputData: $model->input_data ?? [],
            outputData: $model->output_data ?? [],
            errorMessage: $model->error_message,
            attemptNumber: $model->attempt_number ?? 1,
            createdAt: $model->created_at
                ? Timestamp::fromDateTime($model->created_at)
                : null,
        );
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(WorkflowExecution $execution): array
    {
        return [
            'workflow_id' => $execution->workflowId(),
            'trigger_type' => $execution->triggerType(),
            'trigger_record_id' => $execution->triggerRecordId(),
            'trigger_record_type' => $execution->triggerRecordType(),
            'status' => $execution->status()->value,
            'queued_at' => $execution->queuedAt()?->toDateTimeString(),
            'started_at' => $execution->startedAt()?->toDateTimeString(),
            'completed_at' => $execution->completedAt()?->toDateTimeString(),
            'duration_ms' => $execution->durationMs(),
            'context_data' => $execution->contextData(),
            'steps_completed' => $execution->stepsCompleted(),
            'steps_failed' => $execution->stepsFailed(),
            'steps_skipped' => $execution->stepsSkipped(),
            'error_message' => $execution->errorMessage(),
            'triggered_by' => $execution->triggeredBy()?->value(),
        ];
    }
}
