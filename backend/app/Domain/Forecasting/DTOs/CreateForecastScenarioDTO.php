<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\DTOs;

use App\Domain\Forecasting\ValueObjects\ScenarioType;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Data Transfer Object for creating a forecast scenario.
 */
final readonly class CreateForecastScenarioDTO implements JsonSerializable
{
    public function __construct(
        public string $name,
        public int $userId,
        public int $moduleId,
        public DateTimeImmutable $periodStart,
        public DateTimeImmutable $periodEnd,
        public ScenarioType $scenarioType = ScenarioType::CUSTOM,
        public ?string $description = null,
        public ?float $targetAmount = null,
        public bool $isBaseline = false,
        public bool $isShared = false,
        public array $settings = [],
    ) {
        $this->validate();
    }

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? throw new InvalidArgumentException('Name is required'),
            userId: (int) ($data['user_id'] ?? throw new InvalidArgumentException('User ID is required')),
            moduleId: (int) ($data['module_id'] ?? throw new InvalidArgumentException('Module ID is required')),
            periodStart: isset($data['period_start'])
                ? ($data['period_start'] instanceof DateTimeImmutable
                    ? $data['period_start']
                    : new DateTimeImmutable($data['period_start']))
                : throw new InvalidArgumentException('Period start is required'),
            periodEnd: isset($data['period_end'])
                ? ($data['period_end'] instanceof DateTimeImmutable
                    ? $data['period_end']
                    : new DateTimeImmutable($data['period_end']))
                : throw new InvalidArgumentException('Period end is required'),
            scenarioType: isset($data['scenario_type'])
                ? ScenarioType::from($data['scenario_type'])
                : ScenarioType::CUSTOM,
            description: $data['description'] ?? null,
            targetAmount: isset($data['target_amount']) ? (float) $data['target_amount'] : null,
            isBaseline: (bool) ($data['is_baseline'] ?? false),
            isShared: (bool) ($data['is_shared'] ?? false),
            settings: $data['settings'] ?? [],
        );
    }

    /**
     * Validate the DTO.
     */
    private function validate(): void
    {
        if (empty(trim($this->name))) {
            throw new InvalidArgumentException('Scenario name cannot be empty');
        }

        if (strlen($this->name) > 255) {
            throw new InvalidArgumentException('Scenario name cannot exceed 255 characters');
        }

        if ($this->userId < 1) {
            throw new InvalidArgumentException('User ID must be a positive integer');
        }

        if ($this->moduleId < 1) {
            throw new InvalidArgumentException('Module ID must be a positive integer');
        }

        if ($this->periodStart > $this->periodEnd) {
            throw new InvalidArgumentException('Period start must be before or equal to period end');
        }

        if ($this->targetAmount !== null && $this->targetAmount < 0) {
            throw new InvalidArgumentException('Target amount cannot be negative');
        }
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'user_id' => $this->userId,
            'module_id' => $this->moduleId,
            'period_start' => $this->periodStart->format('Y-m-d'),
            'period_end' => $this->periodEnd->format('Y-m-d'),
            'scenario_type' => $this->scenarioType->value,
            'description' => $this->description,
            'target_amount' => $this->targetAmount,
            'is_baseline' => $this->isBaseline,
            'is_shared' => $this->isShared,
            'settings' => $this->settings,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
