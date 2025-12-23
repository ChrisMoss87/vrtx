<?php

declare(strict_types=1);

namespace App\Domain\Goal\Events;

use App\Domain\Shared\Events\DomainEvent;

final class GoalStatusChanged extends DomainEvent
{
    public function __construct(
        private readonly int $goalId,
        private readonly string $previousStatus,
        private readonly string $newStatus,
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
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
        ];
    }

    public function getGoalId(): int
    {
        return $this->goalId;
    }

    public function getPreviousStatus(): string
    {
        return $this->previousStatus;
    }

    public function getNewStatus(): string
    {
        return $this->newStatus;
    }
}
