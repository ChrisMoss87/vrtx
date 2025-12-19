<?php

declare(strict_types=1);

namespace App\Domain\Workflow\DTOs;

use App\Domain\Workflow\ValueObjects\ActionConfig;
use App\Domain\Workflow\ValueObjects\ActionType;
use App\Domain\Workflow\ValueObjects\StepConditions;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Data Transfer Object for creating a workflow step.
 */
final readonly class CreateWorkflowStepDTO implements JsonSerializable
{
    /**
     * @param ActionType $actionType Type of action
     * @param int $order Step order/position
     * @param string|null $name Optional step name
     * @param ActionConfig $actionConfig Action configuration
     * @param StepConditions $conditions Step conditions
     * @param string|null $branchId Branch identifier for conditional flows
     * @param bool $isParallel Execute in parallel with adjacent steps
     * @param bool $continueOnError Continue workflow if this step fails
     * @param int $retryCount Number of retry attempts on failure
     * @param int $retryDelaySeconds Delay between retries
     */
    public function __construct(
        public ActionType $actionType,
        public int $order = 0,
        public ?string $name = null,
        public ActionConfig $actionConfig = new ActionConfig(),
        public StepConditions $conditions = new StepConditions(),
        public ?string $branchId = null,
        public bool $isParallel = false,
        public bool $continueOnError = false,
        public int $retryCount = 0,
        public int $retryDelaySeconds = 60,
    ) {
        $this->validate();
    }

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            actionType: isset($data['action_type'])
                ? ActionType::from($data['action_type'])
                : throw new InvalidArgumentException('Action type is required'),
            order: (int) ($data['order'] ?? 0),
            name: $data['name'] ?? null,
            actionConfig: isset($data['action_config'])
                ? ActionConfig::fromArray($data['action_config'])
                : new ActionConfig(),
            conditions: isset($data['conditions'])
                ? StepConditions::fromArray($data['conditions'])
                : new StepConditions(),
            branchId: $data['branch_id'] ?? null,
            isParallel: (bool) ($data['is_parallel'] ?? false),
            continueOnError: (bool) ($data['continue_on_error'] ?? false),
            retryCount: (int) ($data['retry_count'] ?? 0),
            retryDelaySeconds: (int) ($data['retry_delay_seconds'] ?? 60),
        );
    }

    /**
     * Validate the DTO.
     */
    private function validate(): void
    {
        if ($this->order < 0) {
            throw new InvalidArgumentException('Order cannot be negative');
        }

        if ($this->name !== null && strlen($this->name) > 255) {
            throw new InvalidArgumentException('Step name cannot exceed 255 characters');
        }

        if ($this->retryCount < 0) {
            throw new InvalidArgumentException('Retry count cannot be negative');
        }

        if ($this->retryDelaySeconds < 0) {
            throw new InvalidArgumentException('Retry delay cannot be negative');
        }
    }

    public function toArray(): array
    {
        return [
            'action_type' => $this->actionType->value,
            'order' => $this->order,
            'name' => $this->name,
            'action_config' => $this->actionConfig->toArray(),
            'conditions' => $this->conditions->toArray(),
            'branch_id' => $this->branchId,
            'is_parallel' => $this->isParallel,
            'continue_on_error' => $this->continueOnError,
            'retry_count' => $this->retryCount,
            'retry_delay_seconds' => $this->retryDelaySeconds,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
