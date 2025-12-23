<?php

declare(strict_types=1);

namespace App\Domain\Goal\Events;

use App\Domain\Shared\Events\DomainEvent;

final class GoalProgressUpdated extends DomainEvent
{
    public function __construct(
        private readonly int $goalId,
        private readonly float $previousValue,
        private readonly float $newValue,
        private readonly float $changeAmount,
        private readonly float $attainmentPercent,
        private readonly ?string $source = null,
        private readonly ?int $sourceRecordId = null,
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
            'previous_value' => $this->previousValue,
            'new_value' => $this->newValue,
            'change_amount' => $this->changeAmount,
            'attainment_percent' => $this->attainmentPercent,
            'source' => $this->source,
            'source_record_id' => $this->sourceRecordId,
        ];
    }

    public function getGoalId(): int
    {
        return $this->goalId;
    }

    public function getPreviousValue(): float
    {
        return $this->previousValue;
    }

    public function getNewValue(): float
    {
        return $this->newValue;
    }

    public function getChangeAmount(): float
    {
        return $this->changeAmount;
    }

    public function getAttainmentPercent(): float
    {
        return $this->attainmentPercent;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getSourceRecordId(): ?int
    {
        return $this->sourceRecordId;
    }
}
