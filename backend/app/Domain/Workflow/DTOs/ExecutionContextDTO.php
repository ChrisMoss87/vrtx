<?php

declare(strict_types=1);

namespace App\Domain\Workflow\DTOs;

use JsonSerializable;

/**
 * Data Transfer Object for workflow execution context.
 *
 * Contains all the data needed during workflow execution.
 */
final readonly class ExecutionContextDTO implements JsonSerializable
{
    /**
     * @param int $workflowId Workflow being executed
     * @param string $triggerType Type of trigger that started this execution
     * @param int|null $recordId ID of the record that triggered the workflow
     * @param string|null $recordType Type/module of the triggering record
     * @param array<string, mixed> $recordData Current record data
     * @param array<string, mixed>|null $previousData Previous record data (for updates)
     * @param int|null $triggeredByUserId User who triggered the workflow (for manual triggers)
     * @param array<string, mixed> $variables Variables accumulated during execution
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        public int $workflowId,
        public string $triggerType,
        public ?int $recordId = null,
        public ?string $recordType = null,
        public array $recordData = [],
        public ?array $previousData = null,
        public ?int $triggeredByUserId = null,
        public array $variables = [],
        public array $metadata = [],
    ) {}

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            workflowId: (int) ($data['workflow_id'] ?? 0),
            triggerType: $data['trigger_type'] ?? 'unknown',
            recordId: isset($data['record_id']) ? (int) $data['record_id'] : null,
            recordType: $data['record_type'] ?? null,
            recordData: $data['record_data'] ?? [],
            previousData: $data['previous_data'] ?? null,
            triggeredByUserId: isset($data['triggered_by_user_id'])
                ? (int) $data['triggered_by_user_id']
                : null,
            variables: $data['variables'] ?? [],
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * Create a new context with an added variable.
     */
    public function withVariable(string $key, mixed $value): self
    {
        return new self(
            workflowId: $this->workflowId,
            triggerType: $this->triggerType,
            recordId: $this->recordId,
            recordType: $this->recordType,
            recordData: $this->recordData,
            previousData: $this->previousData,
            triggeredByUserId: $this->triggeredByUserId,
            variables: array_merge($this->variables, [$key => $value]),
            metadata: $this->metadata,
        );
    }

    /**
     * Create a new context with updated record data.
     */
    public function withRecordData(array $recordData): self
    {
        return new self(
            workflowId: $this->workflowId,
            triggerType: $this->triggerType,
            recordId: $this->recordId,
            recordType: $this->recordType,
            recordData: $recordData,
            previousData: $this->previousData,
            triggeredByUserId: $this->triggeredByUserId,
            variables: $this->variables,
            metadata: $this->metadata,
        );
    }

    /**
     * Get a variable value.
     */
    public function getVariable(string $key, mixed $default = null): mixed
    {
        return $this->variables[$key] ?? $default;
    }

    /**
     * Check if a field changed.
     */
    public function fieldChanged(string $field): bool
    {
        if ($this->previousData === null) {
            return false;
        }

        $oldValue = $this->previousData[$field] ?? null;
        $newValue = $this->recordData[$field] ?? null;

        return $oldValue !== $newValue;
    }

    /**
     * Get the old value of a field.
     */
    public function getOldValue(string $field): mixed
    {
        return $this->previousData[$field] ?? null;
    }

    /**
     * Get the new value of a field.
     */
    public function getNewValue(string $field): mixed
    {
        return $this->recordData[$field] ?? null;
    }

    /**
     * Get all changed fields.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function getChangedFields(): array
    {
        if ($this->previousData === null) {
            return [];
        }

        $changed = [];
        $allKeys = array_unique(array_merge(
            array_keys($this->recordData),
            array_keys($this->previousData)
        ));

        foreach ($allKeys as $key) {
            $oldValue = $this->previousData[$key] ?? null;
            $newValue = $this->recordData[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changed[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changed;
    }

    /**
     * Check if this is a create operation.
     */
    public function isCreate(): bool
    {
        return $this->previousData === null;
    }

    /**
     * Check if this is an update operation.
     */
    public function isUpdate(): bool
    {
        return $this->previousData !== null;
    }

    public function toArray(): array
    {
        return [
            'workflow_id' => $this->workflowId,
            'trigger_type' => $this->triggerType,
            'record_id' => $this->recordId,
            'record_type' => $this->recordType,
            'record_data' => $this->recordData,
            'previous_data' => $this->previousData,
            'triggered_by_user_id' => $this->triggeredByUserId,
            'variables' => $this->variables,
            'metadata' => $this->metadata,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
