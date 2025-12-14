<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a forecast scenario is created.
 */
final class ForecastScenarioCreated extends DomainEvent
{
    public function __construct(
        private readonly int $scenarioId,
        private readonly int $userId,
        private readonly int $moduleId,
        private readonly string $scenarioType,
        private readonly bool $isBaseline,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int
    {
        return $this->scenarioId;
    }

    public function aggregateType(): string
    {
        return 'ForecastScenario';
    }

    public function scenarioId(): int
    {
        return $this->scenarioId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function moduleId(): int
    {
        return $this->moduleId;
    }

    public function scenarioType(): string
    {
        return $this->scenarioType;
    }

    public function isBaseline(): bool
    {
        return $this->isBaseline;
    }

    public function toPayload(): array
    {
        return [
            'scenario_id' => $this->scenarioId,
            'user_id' => $this->userId,
            'module_id' => $this->moduleId,
            'scenario_type' => $this->scenarioType,
            'is_baseline' => $this->isBaseline,
        ];
    }
}
