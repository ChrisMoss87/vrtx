<?php

declare(strict_types=1);

namespace App\Domain\Goal\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class GoalAchieved extends DomainEvent
{
    public function __construct(
        private readonly int $goalId,
        private readonly string $name,
        private readonly float $targetValue,
        private readonly float $achievedValue,
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
            'name' => $this->name,
            'target_value' => $this->targetValue,
            'achieved_value' => $this->achievedValue,
            'achieved_at' => $this->achievedAt->format('Y-m-d H:i:s'),
        ];
    }

    public function getGoalId(): int
    {
        return $this->goalId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTargetValue(): float
    {
        return $this->targetValue;
    }

    public function getAchievedValue(): float
    {
        return $this->achievedValue;
    }

    public function getAchievedAt(): DateTimeImmutable
    {
        return $this->achievedAt;
    }
}
