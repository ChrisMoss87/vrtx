<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Workflow;

use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Workflow\Entities\WorkflowExecution;
use App\Domain\Workflow\Entities\WorkflowStepLog;
use App\Domain\Workflow\Repositories\WorkflowExecutionRepositoryInterface;
use App\Domain\Workflow\ValueObjects\ActionType;
use App\Domain\Workflow\ValueObjects\ExecutionStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Query Builder implementation of the WorkflowExecutionRepository.
 */
class DbWorkflowExecutionRepository implements WorkflowExecutionRepositoryInterface
{
    private const TABLE = 'workflow_executions';
    private const STEP_LOGS_TABLE = 'workflow_step_logs';

    public function findById(int $id): ?WorkflowExecution
    {
        $record = DB::table(self::TABLE)
            ->where('id', $id)
            ->first();

        if (!$record) {
            return null;
        }

        return $this->toDomainEntity($record);
    }

    public function findByWorkflowId(int $workflowId, int $limit = 50): array
    {
        $records = DB::table(self::TABLE)
            ->where('workflow_id', $workflowId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return array_map(fn($r) => $this->toDomainEntity($r), $records->all());
    }

    public function findByStatus(ExecutionStatus $status, int $limit = 100): array
    {
        $records = DB::table(self::TABLE)
            ->where('status', $status->value)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return array_map(fn($r) => $this->toDomainEntity($r), $records->all());
    }

    public function findRecent(int $days = 7, int $limit = 100): array
    {
        $cutoff = now()->subDays($days)->toDateTimeString();

        $records = DB::table(self::TABLE)
            ->where('created_at', '>=', $cutoff)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return array_map(fn($r) => $this->toDomainEntity($r), $records->all());
    }

    public function findByRecord(int $recordId, string $recordType, int $limit = 50): array
    {
        $records = DB::table(self::TABLE)
            ->where('trigger_record_id', $recordId)
            ->where('trigger_record_type', $recordType)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return array_map(fn($r) => $this->toDomainEntity($r), $records->all());
    }

    public function save(WorkflowExecution $execution): WorkflowExecution
    {
        $data = $this->toModelData($execution);
        $now = now()->toDateTimeString();

        if ($execution->getId() !== null) {
            $data['updated_at'] = $now;

            DB::table(self::TABLE)
                ->where('id', $execution->getId())
                ->update($data);

            $id = $execution->getId();
        } else {
            $data['created_at'] = $now;
            $data['updated_at'] = $now;

            $id = DB::table(self::TABLE)->insertGetId($data);
        }

        $record = DB::table(self::TABLE)->where('id', $id)->first();
        return $this->toDomainEntity($record);
    }

    public function delete(int $id): bool
    {
        $record = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$record) {
            return false;
        }

        // Delete step logs first
        DB::table(self::STEP_LOGS_TABLE)->where('execution_id', $id)->delete();

        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
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
            'input_data' => json_encode($log->inputData()),
            'output_data' => json_encode($log->outputData()),
            'error_message' => $log->errorMessage(),
            'attempt_number' => $log->attemptNumber(),
        ];

        $now = now()->toDateTimeString();

        if ($log->getId() !== null) {
            $data['updated_at'] = $now;

            DB::table(self::STEP_LOGS_TABLE)
                ->where('id', $log->getId())
                ->update($data);

            $id = $log->getId();
        } else {
            $data['created_at'] = $now;
            $data['updated_at'] = $now;

            $id = DB::table(self::STEP_LOGS_TABLE)->insertGetId($data);
        }

        $record = DB::table(self::STEP_LOGS_TABLE)->where('id', $id)->first();
        return $this->stepLogToDomainEntity($record);
    }

    public function findStepLogs(int $executionId): array
    {
        $records = DB::table(self::STEP_LOGS_TABLE)
            ->where('execution_id', $executionId)
            ->orderBy('created_at')
            ->get();

        return array_map(fn($r) => $this->stepLogToDomainEntity($r), $records->all());
    }

    public function getStatisticsForWorkflow(int $workflowId, ?DateTimeImmutable $since = null): array
    {
        $baseQuery = DB::table(self::TABLE)->where('workflow_id', $workflowId);

        if ($since !== null) {
            $baseQuery->where('created_at', '>=', $since->format('Y-m-d H:i:s'));
        }

        $total = (clone $baseQuery)->count();
        $completed = (clone $baseQuery)->where('status', ExecutionStatus::COMPLETED->value)->count();
        $failed = (clone $baseQuery)->where('status', ExecutionStatus::FAILED->value)->count();
        $cancelled = (clone $baseQuery)->where('status', ExecutionStatus::CANCELLED->value)->count();

        $avgDuration = (clone $baseQuery)
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

        // Get old execution IDs
        $executionIds = DB::table(self::TABLE)
            ->where('created_at', '<', $cutoffString)
            ->pluck('id')
            ->all();

        if (!empty($executionIds)) {
            // Delete step logs for old executions
            DB::table(self::STEP_LOGS_TABLE)
                ->whereIn('execution_id', $executionIds)
                ->delete();
        }

        // Delete old executions
        return DB::table(self::TABLE)
            ->where('created_at', '<', $cutoffString)
            ->delete();
    }

    /**
     * Convert a database record to a domain entity.
     */
    private function toDomainEntity(stdClass $record): WorkflowExecution
    {
        $contextData = is_string($record->context_data ?? null)
            ? json_decode($record->context_data, true)
            : $this->toArray($record->context_data);

        return WorkflowExecution::reconstitute(
            id: $record->id,
            workflowId: $record->workflow_id,
            triggerType: $record->trigger_type,
            triggerRecordId: $record->trigger_record_id,
            triggerRecordType: $record->trigger_record_type,
            status: ExecutionStatus::from($record->status),
            queuedAt: $record->queued_at
                ? Timestamp::fromString($record->queued_at)
                : null,
            startedAt: $record->started_at
                ? Timestamp::fromString($record->started_at)
                : null,
            completedAt: $record->completed_at
                ? Timestamp::fromString($record->completed_at)
                : null,
            durationMs: $record->duration_ms,
            contextData: $contextData ?? [],
            stepsCompleted: $record->steps_completed ?? 0,
            stepsFailed: $record->steps_failed ?? 0,
            stepsSkipped: $record->steps_skipped ?? 0,
            errorMessage: $record->error_message,
            triggeredBy: $record->triggered_by ? UserId::fromInt($record->triggered_by) : null,
            createdAt: $record->created_at
                ? Timestamp::fromString($record->created_at)
                : null,
        );
    }

    /**
     * Convert a StepLog record to domain entity.
     */
    private function stepLogToDomainEntity(stdClass $record): WorkflowStepLog
    {
        $inputData = is_string($record->input_data ?? null)
            ? json_decode($record->input_data, true)
            : $this->toArray($record->input_data);

        $outputData = is_string($record->output_data ?? null)
            ? json_decode($record->output_data, true)
            : $this->toArray($record->output_data);

        return WorkflowStepLog::reconstitute(
            id: $record->id,
            executionId: $record->execution_id,
            stepId: $record->step_id,
            actionType: ActionType::from($record->action_type),
            status: $record->status,
            startedAt: $record->started_at
                ? Timestamp::fromString($record->started_at)
                : null,
            completedAt: $record->completed_at
                ? Timestamp::fromString($record->completed_at)
                : null,
            durationMs: $record->duration_ms,
            inputData: $inputData ?? [],
            outputData: $outputData ?? [],
            errorMessage: $record->error_message,
            attemptNumber: $record->attempt_number ?? 1,
            createdAt: $record->created_at
                ? Timestamp::fromString($record->created_at)
                : null,
        );
    }

    /**
     * Convert a domain entity to database data.
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
            'context_data' => json_encode($execution->contextData()),
            'steps_completed' => $execution->stepsCompleted(),
            'steps_failed' => $execution->stepsFailed(),
            'steps_skipped' => $execution->stepsSkipped(),
            'error_message' => $execution->errorMessage(),
            'triggered_by' => $execution->triggeredBy()?->value(),
        ];
    }

    /**
     * Convert stdClass or array to array.
     */
    private function toArray(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof stdClass) {
            return json_decode(json_encode($value), true);
        }

        return null;
    }
}
