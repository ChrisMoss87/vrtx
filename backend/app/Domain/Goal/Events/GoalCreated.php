<?php

declare(strict_types=1);

namespace App\Domain\Goal\Events;

use App\Domain\Shared\Events\DomainEvent;

final class GoalCreated extends DomainEvent
{
    public function __construct(
        private readonly int $goalId,
        private readonly string $name,
        private readonly string $goalType,
        private readonly ?int $userId,
        private readonly ?int $teamId,
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
            'goal_type' => $this->goalType,
            'user_id' => $this->userId,
            'team_id' => $this->teamId,
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

    public function getGoalType(): string
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
}
