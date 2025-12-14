<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Entities;

use App\Domain\Forecasting\ValueObjects\ScenarioType;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;

/**
 * ForecastScenario aggregate root entity.
 *
 * Represents a forecast scenario for revenue planning and analysis.
 * A scenario can be baseline (current state) or hypothetical (what-if analysis).
 */
final class ForecastScenario implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private string $name,
        private ?string $description,
        private UserId $userId,
        private int $moduleId,
        private DateTimeImmutable $periodStart,
        private DateTimeImmutable $periodEnd,
        private ScenarioType $scenarioType,
        private bool $isBaseline,
        private bool $isShared,
        private float $totalWeighted,
        private float $totalUnweighted,
        private ?float $targetAmount,
        private int $dealCount,
        private array $settings,
        private ?Timestamp $createdAt,
        private ?Timestamp $updatedAt,
    ) {}

    /**
     * Create a new forecast scenario.
     */
    public static function create(
        string $name,
        UserId $userId,
        int $moduleId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        ScenarioType $scenarioType = ScenarioType::CUSTOM,
        ?string $description = null,
        ?float $targetAmount = null,
        bool $isBaseline = false,
        bool $isShared = false,
        array $settings = [],
    ): self {
        return new self(
            id: null,
            name: $name,
            description: $description,
            userId: $userId,
            moduleId: $moduleId,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            scenarioType: $scenarioType,
            isBaseline: $isBaseline,
            isShared: $isShared,
            totalWeighted: 0.0,
            totalUnweighted: 0.0,
            targetAmount: $targetAmount,
            dealCount: 0,
            settings: $settings,
            createdAt: Timestamp::now(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        string $name,
        ?string $description,
        UserId $userId,
        int $moduleId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        ScenarioType $scenarioType,
        bool $isBaseline,
        bool $isShared,
        float $totalWeighted,
        float $totalUnweighted,
        ?float $targetAmount,
        int $dealCount,
        array $settings,
        ?Timestamp $createdAt,
        ?Timestamp $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            description: $description,
            userId: $userId,
            moduleId: $moduleId,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            scenarioType: $scenarioType,
            isBaseline: $isBaseline,
            isShared: $isShared,
            totalWeighted: $totalWeighted,
            totalUnweighted: $totalUnweighted,
            targetAmount: $targetAmount,
            dealCount: $dealCount,
            settings: $settings,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Update scenario details.
     */
    public function update(
        string $name,
        ?string $description,
        ?float $targetAmount,
        array $settings = [],
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->targetAmount = $targetAmount;
        $this->settings = $settings;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Recalculate totals based on deals.
     */
    public function recalculateTotals(
        float $totalWeighted,
        float $totalUnweighted,
        int $dealCount
    ): void {
        $this->totalWeighted = $totalWeighted;
        $this->totalUnweighted = $totalUnweighted;
        $this->dealCount = $dealCount;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Set as baseline scenario.
     */
    public function setAsBaseline(): void
    {
        $this->isBaseline = true;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Remove baseline status.
     */
    public function removeBaseline(): void
    {
        $this->isBaseline = false;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Share scenario with team.
     */
    public function share(): void
    {
        $this->isShared = true;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Unshare scenario.
     */
    public function unshare(): void
    {
        $this->isShared = false;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Calculate gap to target.
     */
    public function getGapAmount(): float
    {
        if ($this->targetAmount === null || $this->targetAmount === 0.0) {
            return 0.0;
        }

        return $this->targetAmount - $this->totalWeighted;
    }

    /**
     * Calculate progress percentage.
     */
    public function getProgressPercent(): float
    {
        if ($this->targetAmount === null || $this->targetAmount === 0.0) {
            return 0.0;
        }

        return min(100.0, ($this->totalWeighted / $this->targetAmount) * 100.0);
    }

    /**
     * Check if target is met.
     */
    public function isTargetMet(): bool
    {
        if ($this->targetAmount === null) {
            return false;
        }

        return $this->totalWeighted >= $this->targetAmount;
    }

    // ========== AggregateRoot Implementation ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function equals(\App\Domain\Shared\Contracts\Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->id !== null && $this->id === $other->id;
    }

    // ========== Getters ==========

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function moduleId(): int
    {
        return $this->moduleId;
    }

    public function periodStart(): DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function periodEnd(): DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function scenarioType(): ScenarioType
    {
        return $this->scenarioType;
    }

    public function isBaseline(): bool
    {
        return $this->isBaseline;
    }

    public function isShared(): bool
    {
        return $this->isShared;
    }

    public function totalWeighted(): float
    {
        return $this->totalWeighted;
    }

    public function totalUnweighted(): float
    {
        return $this->totalUnweighted;
    }

    public function targetAmount(): ?float
    {
        return $this->targetAmount;
    }

    public function dealCount(): int
    {
        return $this->dealCount;
    }

    public function settings(): array
    {
        return $this->settings;
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
