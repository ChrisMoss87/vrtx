<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\DTOs;

use JsonSerializable;

/**
 * Data Transfer Object for forecast summary responses.
 */
final readonly class ForecastResponseDTO implements JsonSerializable
{
    public function __construct(
        public array $commit,
        public array $bestCase,
        public array $pipeline,
        public array $weighted,
        public array $closedWon,
        public ?array $quota,
        public array $period,
    ) {}

    /**
     * Create from forecast data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            commit: $data['commit'] ?? ['amount' => 0.0, 'count' => 0, 'deals' => []],
            bestCase: $data['best_case'] ?? ['amount' => 0.0, 'count' => 0, 'deals' => []],
            pipeline: $data['pipeline'] ?? ['amount' => 0.0, 'count' => 0, 'deals' => []],
            weighted: $data['weighted'] ?? ['amount' => 0.0, 'count' => 0],
            closedWon: $data['closed_won'] ?? ['amount' => 0.0, 'count' => 0, 'deals' => []],
            quota: $data['quota'] ?? null,
            period: $data['period'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'commit' => $this->commit,
            'best_case' => $this->bestCase,
            'pipeline' => $this->pipeline,
            'weighted' => $this->weighted,
            'closed_won' => $this->closedWon,
            'quota' => $this->quota,
            'period' => $this->period,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
