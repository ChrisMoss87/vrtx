<?php

declare(strict_types=1);

namespace App\Domain\Workflow\DTOs;

use App\Domain\Workflow\ValueObjects\TriggerConfig;
use App\Domain\Workflow\ValueObjects\TriggerTiming;
use App\Domain\Workflow\ValueObjects\TriggerType;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Data Transfer Object for updating an existing workflow.
 */
final readonly class UpdateWorkflowDTO implements JsonSerializable
{
    /**
     * @param int $id Workflow ID to update
     * @param string $name Workflow name
     * @param TriggerType $triggerType Type of trigger
     * @param string|null $description Optional description
     * @param TriggerConfig $triggerConfig Trigger configuration
     * @param TriggerTiming $triggerTiming When to trigger (create/update/both)
     * @param array<string> $watchedFields Fields to watch for changes
     * @param array<mixed> $conditions Conditions that must be met
     * @param bool|null $isActive Whether workflow is active
     * @param int $priority Execution priority
     * @param bool $stopOnFirstMatch Stop evaluating other workflows if this one matches
     * @param int|null $maxExecutionsPerDay Rate limit
     * @param bool $runOncePerRecord Only run once per record
     * @param bool $allowManualTrigger Allow manual triggering
     * @param int $delaySeconds Delay before execution
     * @param string|null $scheduleCron Cron expression for scheduled workflows
     * @param int|null $updatedBy User ID who is updating this workflow
     * @param array<CreateWorkflowStepDTO>|null $steps Workflow steps (null = don't update steps)
     */
    public function __construct(
        public int $id,
        public string $name,
        public TriggerType $triggerType,
        public ?string $description = null,
        public TriggerConfig $triggerConfig = new TriggerConfig(),
        public TriggerTiming $triggerTiming = TriggerTiming::ALL,
        public array $watchedFields = [],
        public array $conditions = [],
        public ?bool $isActive = null,
        public int $priority = 0,
        public bool $stopOnFirstMatch = false,
        public ?int $maxExecutionsPerDay = null,
        public bool $runOncePerRecord = false,
        public bool $allowManualTrigger = true,
        public int $delaySeconds = 0,
        public ?string $scheduleCron = null,
        public ?int $updatedBy = null,
        public ?array $steps = null,
    ) {
        $this->validate();
    }

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? throw new InvalidArgumentException('ID is required')),
            name: $data['name'] ?? throw new InvalidArgumentException('Name is required'),
            triggerType: isset($data['trigger_type'])
                ? TriggerType::from($data['trigger_type'])
                : throw new InvalidArgumentException('Trigger type is required'),
            description: $data['description'] ?? null,
            triggerConfig: isset($data['trigger_config'])
                ? TriggerConfig::fromArray($data['trigger_config'])
                : new TriggerConfig(),
            triggerTiming: isset($data['trigger_timing'])
                ? TriggerTiming::from($data['trigger_timing'])
                : TriggerTiming::ALL,
            watchedFields: $data['watched_fields'] ?? [],
            conditions: $data['conditions'] ?? [],
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
            priority: (int) ($data['priority'] ?? 0),
            stopOnFirstMatch: (bool) ($data['stop_on_first_match'] ?? false),
            maxExecutionsPerDay: isset($data['max_executions_per_day'])
                ? (int) $data['max_executions_per_day']
                : null,
            runOncePerRecord: (bool) ($data['run_once_per_record'] ?? false),
            allowManualTrigger: (bool) ($data['allow_manual_trigger'] ?? true),
            delaySeconds: (int) ($data['delay_seconds'] ?? 0),
            scheduleCron: $data['schedule_cron'] ?? null,
            updatedBy: isset($data['updated_by']) ? (int) $data['updated_by'] : null,
            steps: isset($data['steps']) && is_array($data['steps'])
                ? array_map(fn($s) => CreateWorkflowStepDTO::fromArray($s), $data['steps'])
                : null,
        );
    }

    /**
     * Validate the DTO.
     */
    private function validate(): void
    {
        if ($this->id < 1) {
            throw new InvalidArgumentException('ID must be a positive integer');
        }

        if (empty(trim($this->name))) {
            throw new InvalidArgumentException('Workflow name cannot be empty');
        }

        if (strlen($this->name) > 255) {
            throw new InvalidArgumentException('Workflow name cannot exceed 255 characters');
        }

        if ($this->delaySeconds < 0) {
            throw new InvalidArgumentException('Delay seconds cannot be negative');
        }

        if ($this->maxExecutionsPerDay !== null && $this->maxExecutionsPerDay < 1) {
            throw new InvalidArgumentException('Max executions per day must be at least 1');
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'trigger_type' => $this->triggerType->value,
            'description' => $this->description,
            'trigger_config' => $this->triggerConfig->toArray(),
            'trigger_timing' => $this->triggerTiming->value,
            'watched_fields' => $this->watchedFields,
            'conditions' => $this->conditions,
            'is_active' => $this->isActive,
            'priority' => $this->priority,
            'stop_on_first_match' => $this->stopOnFirstMatch,
            'max_executions_per_day' => $this->maxExecutionsPerDay,
            'run_once_per_record' => $this->runOncePerRecord,
            'allow_manual_trigger' => $this->allowManualTrigger,
            'delay_seconds' => $this->delaySeconds,
            'schedule_cron' => $this->scheduleCron,
            'updated_by' => $this->updatedBy,
            'steps' => $this->steps !== null
                ? array_map(fn($s) => $s->toArray(), $this->steps)
                : null,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
