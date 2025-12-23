<?php

declare(strict_types=1);

namespace App\Domain\Goal\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class GoalMilestoneAchieved extends DomainEvent
{
    public function __construct(
        private readonly int $goalId,
        private readonly int $milestoneId,
        private readonly string $milestoneName,
        private readonly float $targetValue,
        private readonly DateTimeImmutable $achievedAt,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int|string
    {
        return $this->goalId;
    }

    public function aggregateType(): string
    {
        return 'Goal';
    }

    public function toPayload(): array
    {
        return [
            'goal_id' => $this->goalId,
            'milestone_id' => $this->milestoneId,
            'milestone_name' => $this->milestoneName,
            'target_value' => $this->targetValue,
            'achieved_at' => $this->achievedAt->format('Y-m-d H:i:s'),
        ];
    }

    public function getGoalId(): int
    {
        return $this->goalId;
    }

    public function getMilestoneId(): int
    {
        return $this->milestoneId;
    }

    public function getMilestoneName(): string
    {
        return $this->milestoneName;
    }

    public function getTargetValue(): float
    {
        return $this->targetValue;
    }

    public function getAchievedAt(): DateTimeImmutable
    {
        return $this->achievedAt;
    }
}
