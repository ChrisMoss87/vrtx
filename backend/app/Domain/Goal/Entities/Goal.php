<?php

declare(strict_types=1);

namespace App\Domain\Goal\Entities;

use App\Domain\Goal\Events\GoalAchieved;
use App\Domain\Goal\Events\GoalCreated;
use App\Domain\Goal\Events\GoalMilestoneAchieved;
use App\Domain\Goal\Events\GoalProgressUpdated;
use App\Domain\Goal\Events\GoalStatusChanged;
use App\Domain\Goal\ValueObjects\GoalStatus;
use App\Domain\Goal\ValueObjects\GoalType;
use App\Domain\Goal\ValueObjects\MetricType;
use App\Domain\Goal\ValueObjects\Money;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Contracts\Entity as EntityContract;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;
use InvalidArgumentException;

final class Goal implements AggregateRoot
{
    use HasDomainEvents;

    /** @var array<GoalMilestone> */
    private array $milestones = [];

    /** @var array<GoalProgressLog> */
    private array $progressLogs = [];

    private function __construct(
        private ?int $id,
        private string $name,
        private ?string $description,
        private GoalType $goalType,
        private ?int $userId,
        private ?int $teamId,
        private MetricType $metricType,
        private ?string $metricField,
        private ?string $moduleApiName,
        private float $targetValue,
        private ?string $currency,
        private DateTimeImmutable $startDate,
        private DateTimeImmutable $endDate,
        private float $currentValue,
        private float $attainmentPercent,
        private GoalStatus $status,
        private ?DateTimeImmutable $achievedAt,
        private int $createdBy,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {
        $this->validateInvariants();
    }

    public static function create(
        string $name,
        GoalType $goalType,
        MetricType $metricType,
        float $targetValue,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        int $createdBy,
        ?string $description = null,
        ?int $userId = null,
        ?int $teamId = null,
        ?string $metricField = null,
        ?string $moduleApiName = null,
        ?string $currency = null,
    ): self {
        $goal = new self(
            id: null,
            name: $name,
            description: $description,
            goalType: $goalType,
            userId: $userId,
            teamId: $teamId,
            metricType: $metricType,
            metricField: $metricField,
            moduleApiName: $moduleApiName,
            targetValue: $targetValue,
            currency: $currency,
            startDate: $startDate,
            endDate: $endDate,
            currentValue: 0.0,
            attainmentPercent: 0.0,
            status: GoalStatus::IN_PROGRESS,
            achievedAt: null,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );

        $goal->recordEvent(new GoalCreated(
            goalId: $goal->id ?? 0,
            name: $name,
            goalType: $goalType->value,
            userId: $userId,
            teamId: $teamId,
        ));

        return $goal;
    }

    public static function reconstitute(
        int $id,
        string $name,
        ?string $description,
        GoalType $goalType,
        ?int $userId,
        ?int $teamId,
        MetricType $metricType,
        ?string $metricField,
        ?string $moduleApiName,
        float $targetValue,
        ?string $currency,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        float $currentValue,
        float $attainmentPercent,
        GoalStatus $status,
        ?DateTimeImmutable $achievedAt,
        int $createdBy,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            description: $description,
            goalType: $goalType,
            userId: $userId,
            teamId: $teamId,
            metricType: $metricType,
            metricField: $metricField,
            moduleApiName: $moduleApiName,
            targetValue: $targetValue,
            currency: $currency,
            startDate: $startDate,
            endDate: $endDate,
            currentValue: $currentValue,
            attainmentPercent: $attainmentPercent,
            status: $status,
            achievedAt: $achievedAt,
            createdBy: $createdBy,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // Business logic methods

    public function updateProgress(float $newValue, ?string $source = null, ?int $sourceRecordId = null): self
    {
        if (!$this->status->canUpdateProgress()) {
            throw new InvalidArgumentException(
                sprintf('Cannot update progress for goal in status: %s', $this->status->value)
            );
        }

        if ($newValue < 0) {
            throw new InvalidArgumentException('Progress value cannot be negative');
        }

        $previousValue = $this->currentValue;
        $changeAmount = $newValue - $previousValue;

        $clone = clone $this;
        $clone->currentValue = $newValue;
        $clone->recalculateAttainment();
        $clone->updatedAt = new DateTimeImmutable();

        // Record event
        $clone->recordEvent(new GoalProgressUpdated(
            goalId: $clone->id ?? 0,
            previousValue: $previousValue,
            newValue: $newValue,
            changeAmount: $changeAmount,
            attainmentPercent: $clone->attainmentPercent,
            source: $source,
            sourceRecordId: $sourceRecordId,
        ));

        // Create progress log
        if ($changeAmount != 0) {
            $clone->progressLogs[] = GoalProgressLog::create(
                goalId: $clone->id ?? 0,
                value: $newValue,
                changeAmount: $changeAmount,
                changeSource: $source,
                sourceRecordId: $sourceRecordId,
            );
        }

        // Check if goal is achieved
        if ($clone->currentValue >= $clone->targetValue && $clone->status === GoalStatus::IN_PROGRESS) {
            return $clone->markAsAchieved();
        }

        // Update milestones
        $clone->updateMilestones();

        return $clone;
    }

    public function addProgress(float $amount, ?string $source = null, ?int $sourceRecordId = null): self
    {
        return $this->updateProgress($this->currentValue + $amount, $source, $sourceRecordId);
    }

    public function markAsAchieved(): self
    {
        if ($this->status === GoalStatus::ACHIEVED) {
            return $this;
        }

        if ($this->status->isFinal()) {
            throw new InvalidArgumentException('Cannot mark a final goal as achieved');
        }

        $clone = clone $this;
        $previousStatus = $clone->status;
        $clone->status = GoalStatus::ACHIEVED;
        $clone->achievedAt = new DateTimeImmutable();
        $clone->updatedAt = new DateTimeImmutable();

        $clone->recordEvent(new GoalStatusChanged(
            goalId: $clone->id ?? 0,
            previousStatus: $previousStatus->value,
            newStatus: GoalStatus::ACHIEVED->value,
        ));

        $clone->recordEvent(new GoalAchieved(
            goalId: $clone->id ?? 0,
            name: $clone->name,
            targetValue: $clone->targetValue,
            achievedValue: $clone->currentValue,
            achievedAt: $clone->achievedAt,
        ));

        return $clone;
    }

    public function markAsMissed(): self
    {
        if ($this->status === GoalStatus::MISSED) {
            return $this;
        }

        if ($this->status->isFinal()) {
            throw new InvalidArgumentException('Cannot mark a final goal as missed');
        }

        $clone = clone $this;
        $previousStatus = $clone->status;
        $clone->status = GoalStatus::MISSED;
        $clone->updatedAt = new DateTimeImmutable();

        $clone->recordEvent(new GoalStatusChanged(
            goalId: $clone->id ?? 0,
            previousStatus: $previousStatus->value,
            newStatus: GoalStatus::MISSED->value,
        ));

        return $clone;
    }

    public function pause(): self
    {
        if (!$this->status->canPause()) {
            throw new InvalidArgumentException(
                sprintf('Cannot pause goal in status: %s', $this->status->value)
            );
        }

        $clone = clone $this;
        $previousStatus = $clone->status;
        $clone->status = GoalStatus::PAUSED;
        $clone->updatedAt = new DateTimeImmutable();

        $clone->recordEvent(new GoalStatusChanged(
            goalId: $clone->id ?? 0,
            previousStatus: $previousStatus->value,
            newStatus: GoalStatus::PAUSED->value,
        ));

        return $clone;
    }

    public function resume(): self
    {
        if (!$this->status->canResume()) {
            throw new InvalidArgumentException(
                sprintf('Cannot resume goal in status: %s', $this->status->value)
            );
        }

        $clone = clone $this;
        $previousStatus = $clone->status;
        $clone->status = GoalStatus::IN_PROGRESS;
        $clone->updatedAt = new DateTimeImmutable();

        $clone->recordEvent(new GoalStatusChanged(
            goalId: $clone->id ?? 0,
            previousStatus: $previousStatus->value,
            newStatus: GoalStatus::IN_PROGRESS->value,
        ));

        return $clone;
    }

    public function addMilestone(GoalMilestone $milestone): self
    {
        $clone = clone $this;
        $clone->milestones[] = $milestone;
        $clone->updatedAt = new DateTimeImmutable();

        return $clone;
    }

    public function updateName(string $name): self
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Goal name cannot be empty');
        }

        $clone = clone $this;
        $clone->name = $name;
        $clone->updatedAt = new DateTimeImmutable();

        return $clone;
    }

    public function updateDescription(?string $description): self
    {
        $clone = clone $this;
        $clone->description = $description;
        $clone->updatedAt = new DateTimeImmutable();

        return $clone;
    }

    public function updateDates(DateTimeImmutable $startDate, DateTimeImmutable $endDate): self
    {
        if ($startDate >= $endDate) {
            throw new InvalidArgumentException('Start date must be before end date');
        }

        $clone = clone $this;
        $clone->startDate = $startDate;
        $clone->endDate = $endDate;
        $clone->updatedAt = new DateTimeImmutable();

        return $clone;
    }

    // Computed properties

    public function getDaysRemaining(): int
    {
        $today = new DateTimeImmutable('today');
        if ($today > $this->endDate) {
            return 0;
        }
        return (int) $today->diff($this->endDate)->days;
    }

    public function getProgressPercent(): float
    {
        if ($this->targetValue <= 0) {
            return 0.0;
        }
        return min(100.0, round(($this->currentValue / $this->targetValue) * 100, 1));
    }

    public function isOverdue(): bool
    {
        $today = new DateTimeImmutable('today');
        return $today > $this->endDate && $this->status === GoalStatus::IN_PROGRESS;
    }

    public function getGapToTarget(): float
    {
        return max(0.0, $this->targetValue - $this->currentValue);
    }

    public function getNextMilestone(): ?GoalMilestone
    {
        $unachieved = array_filter($this->milestones, fn($m) => !$m->isAchieved());

        if (empty($unachieved)) {
            return null;
        }

        usort($unachieved, fn($a, $b) => $a->getTargetValue() <=> $b->getTargetValue());

        return $unachieved[0];
    }

    public function isCurrent(): bool
    {
        $today = new DateTimeImmutable('today');
        return $today >= $this->startDate && $today <= $this->endDate;
    }

    public function getMoney(): ?Money
    {
        if (!$this->metricType->requiresCurrency() || $this->currency === null) {
            return null;
        }

        return Money::from($this->currentValue, $this->currency);
    }

    public function getTargetMoney(): ?Money
    {
        if (!$this->metricType->requiresCurrency() || $this->currency === null) {
            return null;
        }

        return Money::from($this->targetValue, $this->currency);
    }

    // Private helper methods

    private function recalculateAttainment(): void
    {
        if ($this->targetValue > 0) {
            $this->attainmentPercent = round(($this->currentValue / $this->targetValue) * 100, 2);
        } else {
            $this->attainmentPercent = 0.0;
        }
    }

    private function updateMilestones(): void
    {
        foreach ($this->milestones as $key => $milestone) {
            if (!$milestone->isAchieved() && $milestone->getTargetValue() <= $this->currentValue) {
                $achievedMilestone = $milestone->markAchieved();
                $this->milestones[$key] = $achievedMilestone;

                $this->recordEvent(new GoalMilestoneAchieved(
                    goalId: $this->id ?? 0,
                    milestoneId: $achievedMilestone->getId() ?? 0,
                    milestoneName: $achievedMilestone->getName(),
                    targetValue: $achievedMilestone->getTargetValue(),
                    achievedAt: $achievedMilestone->getAchievedAt() ?? new DateTimeImmutable(),
                ));
            }
        }
    }

    private function validateInvariants(): void
    {
        if (empty(trim($this->name))) {
            throw new InvalidArgumentException('Goal name cannot be empty');
        }

        if ($this->targetValue <= 0) {
            throw new InvalidArgumentException('Target value must be greater than zero');
        }

        if ($this->startDate >= $this->endDate) {
            throw new InvalidArgumentException('Start date must be before end date');
        }

        if ($this->currentValue < 0) {
            throw new InvalidArgumentException('Current value cannot be negative');
        }

        if ($this->metricType->requiresCurrency() && empty($this->currency)) {
            throw new InvalidArgumentException('Currency is required for revenue metric type');
        }

        if ($this->goalType->isIndividual() && $this->userId === null) {
            throw new InvalidArgumentException('Individual goals must have a user ID');
        }

        if ($this->goalType->isTeam() && $this->teamId === null) {
            throw new InvalidArgumentException('Team goals must have a team ID');
        }
    }

    // Getters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getGoalType(): GoalType
    {
        return $this->goalType;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getTeamId(): ?int
    {
        return $this->teamId;
    }

    public function getMetricType(): MetricType
    {
        return $this->metricType;
    }

    public function getMetricField(): ?string
    {
        return $this->metricField;
    }

    public function getModuleApiName(): ?string
    {
        return $this->moduleApiName;
    }

    public function getTargetValue(): float
    {
        return $this->targetValue;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    public function getCurrentValue(): float
    {
        return $this->currentValue;
    }

    public function getAttainmentPercent(): float
    {
        return $this->attainmentPercent;
    }

    public function getStatus(): GoalStatus
    {
        return $this->status;
    }

    public function getAchievedAt(): ?DateTimeImmutable
    {
        return $this->achievedAt;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return array<GoalMilestone> */
    public function getMilestones(): array
    {
        return $this->milestones;
    }

    /** @return array<GoalProgressLog> */
    public function getProgressLogs(): array
    {
        return $this->progressLogs;
    }

    public function equals(EntityContract $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->id !== null && $this->id === $other->id;
    }
}
