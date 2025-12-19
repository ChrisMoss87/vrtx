<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a sales quota is updated.
 */
final class QuotaUpdated extends DomainEvent
{
    public function __construct(
        private readonly int $quotaId,
        private readonly ?int $userId,
        private readonly ?int $teamId,
        private readonly ?int $pipelineId,
        private readonly float $oldAmount,
        private readonly float $newAmount,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int
    {
        return $this->quotaId;
    }

    public function aggregateType(): string
    {
        return 'SalesQuota';
    }

    public function quotaId(): int
    {
        return $this->quotaId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function teamId(): ?int
    {
        return $this->teamId;
    }

    public function pipelineId(): ?int
    {
        return $this->pipelineId;
    }

    public function oldAmount(): float
    {
        return $this->oldAmount;
    }

    public function newAmount(): float
    {
        return $this->newAmount;
    }

    public function toPayload(): array
    {
        return [
            'quota_id' => $this->quotaId,
            'user_id' => $this->userId,
            'team_id' => $this->teamId,
            'pipeline_id' => $this->pipelineId,
            'old_amount' => $this->oldAmount,
            'new_amount' => $this->newAmount,
        ];
    }
}
