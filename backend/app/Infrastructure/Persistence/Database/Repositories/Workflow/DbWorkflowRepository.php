<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Workflow;

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
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Query Builder implementation of the WorkflowRepository.
 */
class DbWorkflowRepository implements WorkflowRepositoryInterface
{
    private const TABLE = 'workflows';
    private const STEPS_TABLE = 'workflow_steps';
    private const RUN_HISTORY_TABLE = 'workflow_run_history';

    public function findById(int $id): ?Workflow
    {
        $record = DB::table(self::TABLE)
            ->where('id', $id)
            ->first();

        if (!$record) {
            return null;
        }

        // Load steps separately
        $steps = DB::table(self::STEPS_TABLE)
            ->where('workflow_id', $id)
            ->orderBy('order', 'asc')
            ->get();

        return $this->toDomainEntity($record, $steps->all());
    }

    public function findAll(): array
    {
        $records = DB::table(self::TABLE)
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->get();

        return array_map(function ($record) {
            $steps = DB::table(self::STEPS_TABLE)
                ->where('workflow_id', $record->id)
                ->orderBy('order', 'asc')
                ->get();
            return $this->toDomainEntity($record, $steps->all());
        }, $records->all());
    }

    public function findActiveForModule(int $moduleId): array
    {
        $records = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        return array_map(function ($record) {
            $steps = DB::table(self::STEPS_TABLE)
                ->where('workflow_id', $record->id)
                ->orderBy('order', 'asc')
                ->get();
            return $this->toDomainEntity($record, $steps->all());
        }, $records->all());
    }

    public function findByTriggerType(int $moduleId, TriggerType $triggerType): array
    {
        $records = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('is_active', true)
            ->where('trigger_type', $triggerType->value)
            ->orderBy('priority', 'desc')
            ->get();

        return array_map(function ($record) {
            $steps = DB::table(self::STEPS_TABLE)
                ->where('workflow_id', $record->id)
                ->orderBy('order', 'asc')
                ->get();
            return $this->toDomainEntity($record, $steps->all());
        }, $records->all());
    }

    public function findScheduledForExecution(): array
    {
        $now = now()->toDateTimeString();

        $records = DB::table(self::TABLE)
            ->where('is_active', true)
            ->where('trigger_type', TriggerType::TIME_BASED->value)
            ->where(function ($query) use ($now) {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', $now);
            })
            ->orderBy('priority', 'desc')
            ->get();

        return array_map(function ($record) {
            $steps = DB::table(self::STEPS_TABLE)
                ->where('workflow_id', $record->id)
                ->orderBy('order', 'asc')
                ->get();
            return $this->toDomainEntity($record, $steps->all());
        }, $records->all());
    }

    public function save(Workflow $workflow): Workflow
    {
        $data = $this->toModelData($workflow);
        $now = now()->toDateTimeString();

        if ($workflow->getId() !== null) {
            $data['updated_at'] = $now;

            DB::table(self::TABLE)
                ->where('id', $workflow->getId())
                ->update($data);

            $id = $workflow->getId();
        } else {
            $data['created_at'] = $now;
            $data['updated_at'] = $now;

            $id = DB::table(self::TABLE)->insertGetId($data);
        }

        // Save steps if present
        $steps = $workflow->steps();
        if (!empty($steps)) {
            $this->saveSteps($id, $steps);
        }

        // Reload the workflow
        $record = DB::table(self::TABLE)->where('id', $id)->first();
        $stepRecords = DB::table(self::STEPS_TABLE)
            ->where('workflow_id', $id)
            ->orderBy('order', 'asc')
            ->get();

        return $this->toDomainEntity($record, $stepRecords->all());
    }

    public function delete(int $id): bool
    {
        $record = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$record) {
            return false;
        }

        // Delete steps first
        DB::table(self::STEPS_TABLE)->where('workflow_id', $id)->delete();

        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function hasRunForRecord(int $workflowId, int $recordId, string $recordType, ?string $triggerType = null): bool
    {
        $query = DB::table(self::RUN_HISTORY_TABLE)
            ->where('workflow_id', $workflowId)
            ->where('record_id', $recordId)
            ->where('record_type', $recordType);

        if ($triggerType !== null) {
            $query->where('trigger_type', $triggerType);
        }

        return $query->exists();
    }

    public function recordRunForRecord(int $workflowId, int $recordId, string $recordType, string $triggerType): void
    {
        DB::table(self::RUN_HISTORY_TABLE)->insert([
            'workflow_id' => $workflowId,
            'record_id' => $recordId,
            'record_type' => $recordType,
            'trigger_type' => $triggerType,
            'executed_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Convert a database record to a domain entity.
     */
    private function toDomainEntity(stdClass $record, array $stepRecords = []): Workflow
    {
        $triggerConfig = is_string($record->trigger_config ?? null)
            ? json_decode($record->trigger_config, true)
            : $this->toArray($record->trigger_config);

        $watchedFields = is_string($record->watched_fields ?? null)
            ? json_decode($record->watched_fields, true)
            : $this->toArray($record->watched_fields);

        $conditions = is_string($record->conditions ?? null)
            ? json_decode($record->conditions, true)
            : $this->toArray($record->conditions);

        $workflow = Workflow::reconstitute(
            id: $record->id,
            name: $record->name,
            description: $record->description,
            moduleId: $record->module_id,
            isActive: (bool) $record->is_active,
            priority: $record->priority ?? 0,
            triggerType: TriggerType::from($record->trigger_type),
            triggerConfig: TriggerConfig::fromArray($triggerConfig ?? []),
            triggerTiming: TriggerTiming::tryFrom($record->trigger_timing ?? 'all') ?? TriggerTiming::ALL,
            watchedFields: $watchedFields ?? [],
            webhookSecret: $record->webhook_secret,
            stopOnFirstMatch: (bool) ($record->stop_on_first_match ?? false),
            maxExecutionsPerDay: $record->max_executions_per_day,
            executionsToday: $record->executions_today ?? 0,
            executionsTodayDate: $record->executions_today_date
                ? new DateTimeImmutable($record->executions_today_date)
                : null,
            conditions: $conditions ?? [],
            runOncePerRecord: (bool) ($record->run_once_per_record ?? false),
            allowManualTrigger: (bool) ($record->allow_manual_trigger ?? true),
            delaySeconds: $record->delay_seconds ?? 0,
            scheduleCron: $record->schedule_cron,
            lastRunAt: $record->last_run_at
                ? Timestamp::fromString($record->last_run_at)
                : null,
            nextRunAt: $record->next_run_at
                ? Timestamp::fromString($record->next_run_at)
                : null,
            executionCount: $record->execution_count ?? 0,
            successCount: $record->success_count ?? 0,
            failureCount: $record->failure_count ?? 0,
            createdBy: $record->created_by ? UserId::fromInt($record->created_by) : null,
            updatedBy: $record->updated_by ? UserId::fromInt($record->updated_by) : null,
            createdAt: $record->created_at
                ? Timestamp::fromString($record->created_at)
                : null,
            updatedAt: $record->updated_at
                ? Timestamp::fromString($record->updated_at)
                : null,
        );

        // Add steps to workflow
        if (!empty($stepRecords)) {
            $steps = array_map(fn($s) => $this->stepToDomainEntity($s), $stepRecords);
            $workflow->setSteps($steps);
        }

        return $workflow;
    }

    /**
     * Convert a WorkflowStep record to domain entity.
     */
    private function stepToDomainEntity(stdClass $record): WorkflowStep
    {
        $actionConfig = is_string($record->action_config ?? null)
            ? json_decode($record->action_config, true)
            : $this->toArray($record->action_config);

        $conditions = is_string($record->conditions ?? null)
            ? json_decode($record->conditions, true)
            : $this->toArray($record->conditions);

        return WorkflowStep::reconstitute(
            id: $record->id,
            workflowId: $record->workflow_id,
            order: $record->order ?? 0,
            name: $record->name,
            actionType: ActionType::from($record->action_type),
            actionConfig: ActionConfig::fromArray($actionConfig ?? []),
            conditions: StepConditions::fromArray($conditions ?? []),
            branchId: $record->branch_id,
            isParallel: (bool) ($record->is_parallel ?? false),
            continueOnError: (bool) ($record->continue_on_error ?? false),
            retryCount: $record->retry_count ?? 0,
            retryDelaySeconds: $record->retry_delay_seconds ?? 60,
        );
    }

    /**
     * Convert a domain entity to database data.
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
            'trigger_config' => json_encode($workflow->triggerConfig()->toArray()),
            'trigger_timing' => $workflow->triggerTiming()->value,
            'watched_fields' => json_encode($workflow->watchedFields()),
            'webhook_secret' => $workflow->webhookSecret(),
            'stop_on_first_match' => $workflow->stopOnFirstMatch(),
            'max_executions_per_day' => $workflow->maxExecutionsPerDay(),
            'executions_today' => $workflow->executionsToday(),
            'executions_today_date' => $workflow->executionsTodayDate()?->format('Y-m-d'),
            'conditions' => json_encode($workflow->conditions()),
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
    private function saveSteps(int $workflowId, array $steps): void
    {
        // Delete existing steps
        DB::table(self::STEPS_TABLE)->where('workflow_id', $workflowId)->delete();

        $now = now()->toDateTimeString();

        // Create new steps
        foreach ($steps as $step) {
            DB::table(self::STEPS_TABLE)->insert([
                'workflow_id' => $workflowId,
                'order' => $step->order(),
                'name' => $step->name(),
                'action_type' => $step->actionType()->value,
                'action_config' => json_encode($step->actionConfig()->toArray()),
                'conditions' => json_encode($step->conditions()->toArray()),
                'branch_id' => $step->branchId(),
                'is_parallel' => $step->isParallel(),
                'continue_on_error' => $step->continueOnError(),
                'retry_count' => $step->retryCount(),
                'retry_delay_seconds' => $step->retryDelaySeconds(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
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
