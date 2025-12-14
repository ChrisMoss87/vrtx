<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Entities;

use App\Domain\Forecasting\ValueObjects\ForecastPeriod;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;

/**
 * ForecastSnapshot entity.
 *
 * Represents a point-in-time snapshot of forecast data for trend analysis
 * and accuracy tracking.
 */
final class ForecastSnapshot
{
    private function __construct(
        private ?int $id,
        private ?UserId $userId,
        private int $pipelineId,
        private ForecastPeriod $period,
        private DateTimeImmutable $snapshotDate,
        private float $commitAmount,
        private float $bestCaseAmount,
        private float $pipelineAmount,
        private float $weightedAmount,
        private float $closedWonAmount,
        private int $dealCount,
        private array $metadata,
        private ?Timestamp $createdAt,
        private ?Timestamp $updatedAt,
    ) {}

    /**
     * Create a new forecast snapshot.
     */
    public static function create(
        int $pipelineId,
        ForecastPeriod $period,
        DateTimeImmutable $snapshotDate,
        float $commitAmount,
        float $bestCaseAmount,
        float $pipelineAmount,
        float $weightedAmount,
        float $closedWonAmount,
        int $dealCount,
        ?UserId $userId = null,
        array $metadata = [],
    ): self {
        return new self(
            id: null,
            userId: $userId,
            pipelineId: $pipelineId,
            period: $period,
            snapshotDate: $snapshotDate,
            commitAmount: $commitAmount,
            bestCaseAmount: $bestCaseAmount,
            pipelineAmount: $pipelineAmount,
            weightedAmount: $weightedAmount,
            closedWonAmount: $closedWonAmount,
            dealCount: $dealCount,
            metadata: $metadata,
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
        int $pipelineId,
        ForecastPeriod $period,
        DateTimeImmutable $snapshotDate,
        float $commitAmount,
        float $bestCaseAmount,
        float $pipelineAmount,
        float $weightedAmount,
        float $closedWonAmount,
        int $dealCount,
        array $metadata,
        ?Timestamp $createdAt,
        ?Timestamp $updatedAt,
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            pipelineId: $pipelineId,
            period: $period,
            snapshotDate: $snapshotDate,
            commitAmount: $commitAmount,
            bestCaseAmount: $bestCaseAmount,
            pipelineAmount: $pipelineAmount,
            weightedAmount: $weightedAmount,
            closedWonAmount: $closedWonAmount,
            dealCount: $dealCount,
            metadata: $metadata,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Get total forecast amount (commit + best case).
     */
    public function getTotalForecast(): float
    {
        return $this->commitAmount + $this->bestCaseAmount;
    }

    /**
     * Calculate forecast accuracy.
     */
    public function getAccuracy(): ?float
    {
        if ($this->weightedAmount <= 0) {
            return null;
        }

        return round(($this->closedWonAmount / $this->weightedAmount) * 100, 1);
    }

    /**
     * Calculate variance from forecast.
     */
    public function getVariance(): float
    {
        return $this->closedWonAmount - $this->weightedAmount;
    }

    /**
     * Get variance percentage.
     */
    public function getVariancePercent(): ?float
    {
        if ($this->weightedAmount <= 0) {
            return null;
        }

        return round((($this->closedWonAmount - $this->weightedAmount) / $this->weightedAmount) * 100, 1);
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

    public function pipelineId(): int
    {
        return $this->pipelineId;
    }

    public function period(): ForecastPeriod
    {
        return $this->period;
    }

    public function snapshotDate(): DateTimeImmutable
    {
        return $this->snapshotDate;
    }

    public function commitAmount(): float
    {
        return $this->commitAmount;
    }

    public function bestCaseAmount(): float
    {
        return $this->bestCaseAmount;
    }

    public function pipelineAmount(): float
    {
        return $this->pipelineAmount;
    }

    public function weightedAmount(): float
    {
        return $this->weightedAmount;
    }

    public function closedWonAmount(): float
    {
        return $this->closedWonAmount;
    }

    public function dealCount(): int
    {
        return $this->dealCount;
    }

    public function metadata(): array
    {
        return $this->metadata;
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
