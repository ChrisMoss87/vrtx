<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Entities;

use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Events\DomainEvent;
use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Workflow\ValueObjects\TriggerConfig;
use App\Domain\Workflow\ValueObjects\TriggerTiming;
use App\Domain\Workflow\ValueObjects\TriggerType;
use DateTimeImmutable;

/**
 * Workflow aggregate root entity.
 *
 * Represents an automation workflow that can be triggered by various events
 * and executes a sequence of steps.
 */
final class Workflow implements AggregateRoot
{
    use HasDomainEvents;

    /** @var array<WorkflowStep> */
    private array $steps = [];

    private function __construct(
        private ?int $id,
        private string $name,
        private ?string $description,
        private int $moduleId,
        private bool $isActive,
        private int $priority,
        private TriggerType $triggerType,
        private TriggerConfig $triggerConfig,
        private TriggerTiming $triggerTiming,
        private array $watchedFields,
        private ?string $webhookSecret,
        private bool $stopOnFirstMatch,
        private ?int $maxExecutionsPerDay,
        private int $executionsToday,
        private ?DateTimeImmutable $executionsTodayDate,
        private array $conditions,
        private bool $runOncePerRecord,
        private bool $allowManualTrigger,
        private int $delaySeconds,
        private ?string $scheduleCron,
        private ?Timestamp $lastRunAt,
        private ?Timestamp $nextRunAt,
        private int $executionCount,
        private int $successCount,
        private int $failureCount,
        private ?UserId $createdBy,
        private ?UserId $updatedBy,
        private ?Timestamp $createdAt,
        private ?Timestamp $updatedAt,
    ) {}

    /**
     * Create a new workflow.
     */
    public static function create(
        string $name,
        int $moduleId,
        TriggerType $triggerType,
        ?string $description = null,
        ?TriggerConfig $triggerConfig = null,
        ?TriggerTiming $triggerTiming = null,
        array $watchedFields = [],
        array $conditions = [],
        ?UserId $createdBy = null,
    ): self {
        return new self(
            id: null,
            name: $name,
            description: $description,
            moduleId: $moduleId,
            isActive: false,
            priority: 0,
            triggerType: $triggerType,
            triggerConfig: $triggerConfig ?? new TriggerConfig(),
            triggerTiming: $triggerTiming ?? TriggerTiming::ALL,
            watchedFields: $watchedFields,
            webhookSecret: null,
            stopOnFirstMatch: false,
            maxExecutionsPerDay: null,
            executionsToday: 0,
            executionsTodayDate: null,
            conditions: $conditions,
            runOncePerRecord: false,
            allowManualTrigger: true,
            delaySeconds: 0,
            scheduleCron: null,
            lastRunAt: null,
            nextRunAt: null,
            executionCount: 0,
            successCount: 0,
            failureCount: 0,
            createdBy: $createdBy,
            updatedBy: null,
            createdAt: Timestamp::now(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        string $name,
        ?string $description,
        int $moduleId,
        bool $isActive,
        int $priority,
        TriggerType $triggerType,
        TriggerConfig $triggerConfig,
        TriggerTiming $triggerTiming,
        array $watchedFields,
        ?string $webhookSecret,
        bool $stopOnFirstMatch,
        ?int $maxExecutionsPerDay,
        int $executionsToday,
        ?DateTimeImmutable $executionsTodayDate,
        array $conditions,
        bool $runOncePerRecord,
        bool $allowManualTrigger,
        int $delaySeconds,
        ?string $scheduleCron,
        ?Timestamp $lastRunAt,
        ?Timestamp $nextRunAt,
        int $executionCount,
        int $successCount,
        int $failureCount,
        ?UserId $createdBy,
        ?UserId $updatedBy,
        ?Timestamp $createdAt,
        ?Timestamp $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            description: $description,
            moduleId: $moduleId,
            isActive: $isActive,
            priority: $priority,
            triggerType: $triggerType,
            triggerConfig: $triggerConfig,
            triggerTiming: $triggerTiming,
            watchedFields: $watchedFields,
            webhookSecret: $webhookSecret,
            stopOnFirstMatch: $stopOnFirstMatch,
            maxExecutionsPerDay: $maxExecutionsPerDay,
            executionsToday: $executionsToday,
            executionsTodayDate: $executionsTodayDate,
            conditions: $conditions,
            runOncePerRecord: $runOncePerRecord,
            allowManualTrigger: $allowManualTrigger,
            delaySeconds: $delaySeconds,
            scheduleCron: $scheduleCron,
            lastRunAt: $lastRunAt,
            nextRunAt: $nextRunAt,
            executionCount: $executionCount,
            successCount: $successCount,
            failureCount: $failureCount,
            createdBy: $createdBy,
            updatedBy: $updatedBy,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Activate the workflow.
     */
    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Deactivate the workflow.
     */
    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Update workflow details.
     */
    public function update(
        string $name,
        ?string $description,
        TriggerType $triggerType,
        TriggerConfig $triggerConfig,
        TriggerTiming $triggerTiming,
        array $watchedFields,
        array $conditions,
        ?UserId $updatedBy = null,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->triggerType = $triggerType;
        $this->triggerConfig = $triggerConfig;
        $this->triggerTiming = $triggerTiming;
        $this->watchedFields = $watchedFields;
        $this->conditions = $conditions;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Configure execution settings.
     */
    public function configureExecution(
        int $priority,
        bool $stopOnFirstMatch,
        ?int $maxExecutionsPerDay,
        bool $runOncePerRecord,
        bool $allowManualTrigger,
        int $delaySeconds,
    ): void {
        $this->priority = $priority;
        $this->stopOnFirstMatch = $stopOnFirstMatch;
        $this->maxExecutionsPerDay = $maxExecutionsPerDay;
        $this->runOncePerRecord = $runOncePerRecord;
        $this->allowManualTrigger = $allowManualTrigger;
        $this->delaySeconds = $delaySeconds;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Configure scheduling.
     */
    public function configureSchedule(?string $cronExpression, ?Timestamp $nextRunAt = null): void
    {
        $this->scheduleCron = $cronExpression;
        $this->nextRunAt = $nextRunAt;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Generate a new webhook secret.
     */
    public function generateWebhookSecret(): string
    {
        $this->webhookSecret = bin2hex(random_bytes(32));
        $this->updatedAt = Timestamp::now();
        return $this->webhookSecret;
    }

    /**
     * Check if workflow should trigger for the given event.
     */
    public function shouldTriggerFor(string $eventType, bool $isCreate = false): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if (!$this->canExecuteToday()) {
            return false;
        }

        if (!$this->triggerTiming->matches($isCreate)) {
            return false;
        }

        return $this->triggerType->matchesEvent($eventType);
    }

    /**
     * Check if workflow can execute today (rate limiting).
     */
    public function canExecuteToday(): bool
    {
        if ($this->maxExecutionsPerDay === null) {
            return true;
        }

        if ($this->executionsTodayDate === null) {
            return true;
        }

        $today = new DateTimeImmutable('today');
        if ($this->executionsTodayDate < $today) {
            return true;
        }

        return $this->executionsToday < $this->maxExecutionsPerDay;
    }

    /**
     * Increment today's execution counter.
     */
    public function incrementTodayExecutions(): void
    {
        $today = new DateTimeImmutable('today');

        if ($this->executionsTodayDate === null || $this->executionsTodayDate < $today) {
            $this->executionsToday = 1;
            $this->executionsTodayDate = $today;
        } else {
            $this->executionsToday++;
        }
    }

    /**
     * Record an execution result.
     */
    public function recordExecution(bool $success): void
    {
        $this->executionCount++;
        if ($success) {
            $this->successCount++;
        } else {
            $this->failureCount++;
        }
        $this->lastRunAt = Timestamp::now();
    }

    /**
     * Add a step to the workflow.
     */
    public function addStep(WorkflowStep $step): void
    {
        $this->steps[] = $step;
    }

    /**
     * Set all steps at once.
     *
     * @param array<WorkflowStep> $steps
     */
    public function setSteps(array $steps): void
    {
        $this->steps = $steps;
    }

    // ========== AggregateRoot Implementation ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function equals(\App\Domain\Shared\Contracts\Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->id !== null && $this->id === $other->id;
    }

    // ========== Getters ==========

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function moduleId(): int
    {
        return $this->moduleId;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function triggerType(): TriggerType
    {
        return $this->triggerType;
    }

    public function triggerConfig(): TriggerConfig
    {
        return $this->triggerConfig;
    }

    public function triggerTiming(): TriggerTiming
    {
        return $this->triggerTiming;
    }

    /**
     * @return array<string>
     */
    public function watchedFields(): array
    {
        return $this->watchedFields;
    }

    public function webhookSecret(): ?string
    {
        return $this->webhookSecret;
    }

    public function stopOnFirstMatch(): bool
    {
        return $this->stopOnFirstMatch;
    }

    public function maxExecutionsPerDay(): ?int
    {
        return $this->maxExecutionsPerDay;
    }

    public function executionsToday(): int
    {
        return $this->executionsToday;
    }

    public function executionsTodayDate(): ?DateTimeImmutable
    {
        return $this->executionsTodayDate;
    }

    /**
     * @return array<mixed>
     */
    public function conditions(): array
    {
        return $this->conditions;
    }

    public function runOncePerRecord(): bool
    {
        return $this->runOncePerRecord;
    }

    public function allowManualTrigger(): bool
    {
        return $this->allowManualTrigger;
    }

    public function delaySeconds(): int
    {
        return $this->delaySeconds;
    }

    public function scheduleCron(): ?string
    {
        return $this->scheduleCron;
    }

    public function lastRunAt(): ?Timestamp
    {
        return $this->lastRunAt;
    }

    public function nextRunAt(): ?Timestamp
    {
        return $this->nextRunAt;
    }

    public function executionCount(): int
    {
        return $this->executionCount;
    }

    public function successCount(): int
    {
        return $this->successCount;
    }

    public function failureCount(): int
    {
        return $this->failureCount;
    }

    public function createdBy(): ?UserId
    {
        return $this->createdBy;
    }

    public function updatedBy(): ?UserId
    {
        return $this->updatedBy;
    }

    public function createdAt(): ?Timestamp
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?Timestamp
    {
        return $this->updatedAt;
    }

    /**
     * @return array<WorkflowStep>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    /**
     * Get success rate as a percentage.
     */
    public function successRate(): float
    {
        if ($this->executionCount === 0) {
            return 0.0;
        }
        return ($this->successCount / $this->executionCount) * 100;
    }
}
