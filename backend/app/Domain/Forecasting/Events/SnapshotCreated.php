<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a forecast snapshot is created.
 */
final class SnapshotCreated extends DomainEvent
{
    public function __construct(
        private readonly int $snapshotId,
        private readonly int $pipelineId,
        private readonly ?int $userId,
        private readonly string $periodType,
        private readonly string $snapshotDate,
        private readonly float $weightedAmount,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int
    {
        return $this->snapshotId;
    }

    public function aggregateType(): string
    {
        return 'ForecastSnapshot';
    }

    public function snapshotId(): int
    {
        return $this->snapshotId;
    }

    public function pipelineId(): int
    {
        return $this->pipelineId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function periodType(): string
    {
        return $this->periodType;
    }

    public function snapshotDate(): string
    {
        return $this->snapshotDate;
    }

    public function weightedAmount(): float
    {
        return $this->weightedAmount;
    }

    public function toPayload(): array
    {
        return [
            'snapshot_id' => $this->snapshotId,
            'pipeline_id' => $this->pipelineId,
            'user_id' => $this->userId,
            'period_type' => $this->periodType,
            'snapshot_date' => $this->snapshotDate,
            'weighted_amount' => $this->weightedAmount,
        ];
    }
}
