<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a deal forecast is manually adjusted.
 */
final class ForecastAdjusted extends DomainEvent
{
    public function __construct(
        private readonly int $adjustmentId,
        private readonly int $userId,
        private readonly int $moduleRecordId,
        private readonly string $adjustmentType,
        private readonly ?string $oldValue,
        private readonly ?string $newValue,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int
    {
        return $this->adjustmentId;
    }

    public function aggregateType(): string
    {
        return 'ForecastAdjustment';
    }

    public function adjustmentId(): int
    {
        return $this->adjustmentId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function moduleRecordId(): int
    {
        return $this->moduleRecordId;
    }

    public function adjustmentType(): string
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

    public function toPayload(): array
    {
        return [
            'adjustment_id' => $this->adjustmentId,
            'user_id' => $this->userId,
            'module_record_id' => $this->moduleRecordId,
            'adjustment_type' => $this->adjustmentType,
            'old_value' => $this->oldValue,
            'new_value' => $this->newValue,
        ];
    }
}
