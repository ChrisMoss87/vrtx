<?php

declare(strict_types=1);

namespace App\Domain\Workflow\DTOs;

use App\Domain\Workflow\Entities\Workflow;
use App\Domain\Workflow\Entities\WorkflowStep;
use JsonSerializable;

/**
 * Data Transfer Object for workflow API responses.
 */
final readonly class WorkflowResponseDTO implements JsonSerializable
{
    /**
     * @param int $id
     * @param string $name
     * @param string|null $description
     * @param int $moduleId
     * @param bool $isActive
     * @param int $priority
     * @param string $triggerType
     * @param array<string, mixed> $triggerConfig
     * @param string $triggerTiming
     * @param array<string> $watchedFields
     * @param array<mixed> $conditions
     * @param bool $stopOnFirstMatch
     * @param int|null $maxExecutionsPerDay
     * @param bool $runOncePerRecord
     * @param bool $allowManualTrigger
     * @param int $delaySeconds
     * @param string|null $scheduleCron
     * @param string|null $lastRunAt
     * @param string|null $nextRunAt
     * @param int $executionCount
     * @param int $successCount
     * @param int $failureCount
     * @param float $successRate
     * @param int|null $createdBy
     * @param int|null $updatedBy
     * @param string|null $createdAt
     * @param string|null $updatedAt
     * @param array<array<string, mixed>> $steps
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public int $moduleId,
        public bool $isActive,
        public int $priority,
        public string $triggerType,
        public array $triggerConfig,
        public string $triggerTiming,
        public array $watchedFields,
        public array $conditions,
        public bool $stopOnFirstMatch,
        public ?int $maxExecutionsPerDay,
        public bool $runOncePerRecord,
        public bool $allowManualTrigger,
        public int $delaySeconds,
        public ?string $scheduleCron,
        public ?string $lastRunAt,
        public ?string $nextRunAt,
        public int $executionCount,
        public int $successCount,
        public int $failureCount,
        public float $successRate,
        public ?int $createdBy,
        public ?int $updatedBy,
        public ?string $createdAt,
        public ?string $updatedAt,
        public array $steps,
    ) {}

    /**
     * Create from a Workflow entity.
     */
    public static function fromEntity(Workflow $workflow): self
    {
        return new self(
            id: $workflow->getId() ?? 0,
            name: $workflow->name(),
            description: $workflow->description(),
            moduleId: $workflow->moduleId(),
            isActive: $workflow->isActive(),
            priority: $workflow->priority(),
            triggerType: $workflow->triggerType()->value,
            triggerConfig: $workflow->triggerConfig()->toArray(),
            triggerTiming: $workflow->triggerTiming()->value,
            watchedFields: $workflow->watchedFields(),
            conditions: $workflow->conditions(),
            stopOnFirstMatch: $workflow->stopOnFirstMatch(),
            maxExecutionsPerDay: $workflow->maxExecutionsPerDay(),
            runOncePerRecord: $workflow->runOncePerRecord(),
            allowManualTrigger: $workflow->allowManualTrigger(),
            delaySeconds: $workflow->delaySeconds(),
            scheduleCron: $workflow->scheduleCron(),
            lastRunAt: $workflow->lastRunAt()?->toIso8601(),
            nextRunAt: $workflow->nextRunAt()?->toIso8601(),
            executionCount: $workflow->executionCount(),
            successCount: $workflow->successCount(),
            failureCount: $workflow->failureCount(),
            successRate: $workflow->successRate(),
            createdBy: $workflow->createdBy()?->value(),
            updatedBy: $workflow->updatedBy()?->value(),
            createdAt: $workflow->createdAt()?->toIso8601(),
            updatedAt: $workflow->updatedAt()?->toIso8601(),
            steps: array_map(
                fn(WorkflowStep $step) => self::stepToArray($step),
                $workflow->steps()
            ),
        );
    }

    /**
     * Convert a WorkflowStep entity to array.
     *
     * @return array<string, mixed>
     */
    private static function stepToArray(WorkflowStep $step): array
    {
        return [
            'id' => $step->getId(),
            'workflow_id' => $step->workflowId(),
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
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'module_id' => $this->moduleId,
            'is_active' => $this->isActive,
            'priority' => $this->priority,
            'trigger_type' => $this->triggerType,
            'trigger_config' => $this->triggerConfig,
            'trigger_timing' => $this->triggerTiming,
            'watched_fields' => $this->watchedFields,
            'conditions' => $this->conditions,
            'stop_on_first_match' => $this->stopOnFirstMatch,
            'max_executions_per_day' => $this->maxExecutionsPerDay,
            'run_once_per_record' => $this->runOncePerRecord,
            'allow_manual_trigger' => $this->allowManualTrigger,
            'delay_seconds' => $this->delaySeconds,
            'schedule_cron' => $this->scheduleCron,
            'last_run_at' => $this->lastRunAt,
            'next_run_at' => $this->nextRunAt,
            'execution_count' => $this->executionCount,
            'success_count' => $this->successCount,
            'failure_count' => $this->failureCount,
            'success_rate' => $this->successRate,
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'steps' => $this->steps,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
