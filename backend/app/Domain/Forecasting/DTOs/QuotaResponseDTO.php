<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\DTOs;

use App\Domain\Forecasting\Entities\SalesQuota;
use JsonSerializable;

/**
 * Data Transfer Object for quota responses.
 */
final readonly class QuotaResponseDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public ?int $userId,
        public ?int $pipelineId,
        public ?int $teamId,
        public array $period,
        public float $quotaAmount,
        public string $currency,
        public string $quotaType,
        public ?string $notes,
        public ?float $currentAmount,
        public ?float $attainment,
        public ?float $remaining,
        public bool $isAchieved,
    ) {}

    /**
     * Create from entity.
     */
    public static function fromEntity(
        SalesQuota $quota,
        ?float $currentAmount = null
    ): self {
        $attainment = $currentAmount !== null
            ? $quota->getAttainment($currentAmount)
            : null;

        $remaining = $currentAmount !== null
            ? $quota->getRemainingAmount($currentAmount)
            : null;

        $isAchieved = $currentAmount !== null
            ? $quota->isAchieved($currentAmount)
            : false;

        return new self(
            id: $quota->getId(),
            userId: $quota->userId()?->value(),
            pipelineId: $quota->pipelineId(),
            teamId: $quota->teamId(),
            period: $quota->period()->toArray(),
            quotaAmount: $quota->quotaAmount(),
            currency: $quota->currency(),
            quotaType: $quota->quotaType()->value,
            notes: $quota->notes(),
            currentAmount: $currentAmount,
            attainment: $attainment,
            remaining: $remaining,
            isAchieved: $isAchieved,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'pipeline_id' => $this->pipelineId,
            'team_id' => $this->teamId,
            'period' => $this->period,
            'quota_amount' => $this->quotaAmount,
            'currency' => $this->currency,
            'quota_type' => $this->quotaType,
            'notes' => $this->notes,
            'current_amount' => $this->currentAmount,
            'attainment' => $this->attainment,
            'remaining' => $this->remaining,
            'is_achieved' => $this->isAchieved,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
