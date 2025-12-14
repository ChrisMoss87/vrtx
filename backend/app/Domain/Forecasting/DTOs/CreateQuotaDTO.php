<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\DTOs;

use App\Domain\Forecasting\ValueObjects\ForecastPeriod;
use App\Domain\Forecasting\ValueObjects\QuotaType;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Data Transfer Object for creating a sales quota.
 */
final readonly class CreateQuotaDTO implements JsonSerializable
{
    public function __construct(
        public ForecastPeriod $period,
        public float $quotaAmount,
        public QuotaType $quotaType = QuotaType::REVENUE,
        public ?int $userId = null,
        public ?int $pipelineId = null,
        public ?int $teamId = null,
        public string $currency = 'USD',
        public ?string $notes = null,
    ) {
        $this->validate();
    }

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            period: isset($data['period'])
                ? ForecastPeriod::fromArray($data['period'])
                : throw new InvalidArgumentException('Period is required'),
            quotaAmount: isset($data['quota_amount'])
                ? (float) $data['quota_amount']
                : throw new InvalidArgumentException('Quota amount is required'),
            quotaType: isset($data['quota_type'])
                ? QuotaType::from($data['quota_type'])
                : QuotaType::REVENUE,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            pipelineId: isset($data['pipeline_id']) ? (int) $data['pipeline_id'] : null,
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            currency: $data['currency'] ?? 'USD',
            notes: $data['notes'] ?? null,
        );
    }

    /**
     * Validate the DTO.
     */
    private function validate(): void
    {
        if ($this->quotaAmount < 0) {
            throw new InvalidArgumentException('Quota amount cannot be negative');
        }

        // Must have at least one of: user, team, or pipeline
        if ($this->userId === null && $this->teamId === null && $this->pipelineId === null) {
            throw new InvalidArgumentException('Quota must be assigned to a user, team, or pipeline');
        }

        if (empty(trim($this->currency))) {
            throw new InvalidArgumentException('Currency cannot be empty');
        }
    }

    public function toArray(): array
    {
        return [
            'period' => $this->period->toArray(),
            'quota_amount' => $this->quotaAmount,
            'quota_type' => $this->quotaType->value,
            'user_id' => $this->userId,
            'pipeline_id' => $this->pipelineId,
            'team_id' => $this->teamId,
            'currency' => $this->currency,
            'notes' => $this->notes,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
