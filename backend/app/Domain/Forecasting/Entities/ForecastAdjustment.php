<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Entities;

use App\Domain\Forecasting\ValueObjects\AdjustmentType;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;

/**
 * ForecastAdjustment entity.
 *
 * Tracks manual adjustments made to deal forecasts, providing an audit trail
 * of forecast changes.
 */
final class ForecastAdjustment implements Entity
{
    private function __construct(
        private ?int $id,
        private UserId $userId,
        private int $moduleRecordId,
        private AdjustmentType $adjustmentType,
        private ?string $oldValue,
        private ?string $newValue,
        private ?string $reason,
        private ?Timestamp $createdAt,
        private ?Timestamp $updatedAt,
    ) {}

    /**
     * Create a new forecast adjustment.
     */
    public static function create(
        UserId $userId,
        int $moduleRecordId,
        AdjustmentType $adjustmentType,
        ?string $oldValue,
        ?string $newValue,
        ?string $reason = null,
    ): self {
        return new self(
            id: null,
            userId: $userId,
            moduleRecordId: $moduleRecordId,
            adjustmentType: $adjustmentType,
            oldValue: $oldValue,
            newValue: $newValue,
            reason: $reason,
            createdAt: Timestamp::now(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        UserId $userId,
        int $moduleRecordId,
        AdjustmentType $adjustmentType,
        ?string $oldValue,
        ?string $newValue,
        ?string $reason,
        ?Timestamp $createdAt,
        ?Timestamp $updatedAt,
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            moduleRecordId: $moduleRecordId,
            adjustmentType: $adjustmentType,
            oldValue: $oldValue,
            newValue: $newValue,
            reason: $reason,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Get human-readable description of the adjustment.
     */
    public function getDescription(): string
    {
        return match ($this->adjustmentType) {
            AdjustmentType::CATEGORY_CHANGE => "Changed forecast category from '{$this->oldValue}' to '{$this->newValue}'",
            AdjustmentType::AMOUNT_OVERRIDE => "Override amount changed from {$this->oldValue} to {$this->newValue}",
            AdjustmentType::CLOSE_DATE_CHANGE => "Expected close date changed from {$this->oldValue} to {$this->newValue}",
        };
    }

    // ========== Getters ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function moduleRecordId(): int
    {
        return $this->moduleRecordId;
    }

    public function adjustmentType(): AdjustmentType
    {
        return $this->adjustmentType;
    }

    public function oldValue(): ?string
    {
        return $this->oldValue;
    }

    public function newValue(): ?string
    {
        return $this->newValue;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function createdAt(): ?Timestamp
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?Timestamp
    {
        return $this->updatedAt;
    }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->id !== null && $this->id === $other->id;
    }
}
