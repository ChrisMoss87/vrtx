<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Entities;

use App\Domain\Forecasting\ValueObjects\ForecastPeriod;
use App\Domain\Forecasting\ValueObjects\QuotaType;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;

/**
 * SalesQuota entity.
 *
 * Represents a sales quota for a user, team, or pipeline within a specific time period.
 */
final class SalesQuota
{
    private function __construct(
        private ?int $id,
        private ?UserId $userId,
        private ?int $pipelineId,
        private ?int $teamId,
        private ForecastPeriod $period,
        private float $quotaAmount,
        private string $currency,
        private QuotaType $quotaType,
        private ?string $notes,
        private ?Timestamp $createdAt,
        private ?Timestamp $updatedAt,
    ) {}

    /**
     * Create a new sales quota.
     */
    public static function create(
        ForecastPeriod $period,
        float $quotaAmount,
        QuotaType $quotaType = QuotaType::REVENUE,
        ?UserId $userId = null,
        ?int $pipelineId = null,
        ?int $teamId = null,
        string $currency = 'USD',
        ?string $notes = null,
    ): self {
        return new self(
            id: null,
            userId: $userId,
            pipelineId: $pipelineId,
            teamId: $teamId,
            period: $period,
            quotaAmount: $quotaAmount,
            currency: $currency,
            quotaType: $quotaType,
            notes: $notes,
            createdAt: Timestamp::now(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        ?UserId $userId,
        ?int $pipelineId,
        ?int $teamId,
        ForecastPeriod $period,
        float $quotaAmount,
        string $currency,
        QuotaType $quotaType,
        ?string $notes,
        ?Timestamp $createdAt,
        ?Timestamp $updatedAt,
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            pipelineId: $pipelineId,
            teamId: $teamId,
            period: $period,
            quotaAmount: $quotaAmount,
            currency: $currency,
            quotaType: $quotaType,
            notes: $notes,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Update quota details.
     */
    public function update(
        float $quotaAmount,
        ?string $notes = null,
    ): void {
        $this->quotaAmount = $quotaAmount;
        $this->notes = $notes;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Check if this quota is for the current period.
     */
    public function isCurrentPeriod(): bool
    {
        return $this->period->isCurrent();
    }

    /**
     * Get quota attainment percentage.
     */
    public function getAttainment(float $actualAmount): float
    {
        if ($this->quotaAmount <= 0) {
            return 0.0;
        }

        return round(($actualAmount / $this->quotaAmount) * 100, 1);
    }

    /**
     * Get remaining amount to hit quota.
     */
    public function getRemainingAmount(float $actualAmount): float
    {
        return max(0.0, $this->quotaAmount - $actualAmount);
    }

    /**
     * Check if quota has been achieved.
     */
    public function isAchieved(float $actualAmount): bool
    {
        return $actualAmount >= $this->quotaAmount;
    }

    /**
     * Check if this is a user quota.
     */
    public function isUserQuota(): bool
    {
        return $this->userId !== null;
    }

    /**
     * Check if this is a team quota.
     */
    public function isTeamQuota(): bool
    {
        return $this->teamId !== null;
    }

    /**
     * Check if this is a pipeline quota.
     */
    public function isPipelineQuota(): bool
    {
        return $this->pipelineId !== null;
    }

    // ========== Getters ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function userId(): ?UserId
    {
        return $this->userId;
    }

    public function pipelineId(): ?int
    {
        return $this->pipelineId;
    }

    public function teamId(): ?int
    {
        return $this->teamId;
    }

    public function period(): ForecastPeriod
    {
        return $this->period;
    }

    public function quotaAmount(): float
    {
        return $this->quotaAmount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function quotaType(): QuotaType
    {
        return $this->quotaType;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function createdAt(): ?Timestamp
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?Timestamp
    {
        return $this->updatedAt;
    }
}
