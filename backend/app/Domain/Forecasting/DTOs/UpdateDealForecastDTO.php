<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\DTOs;

use App\Domain\Forecasting\ValueObjects\ProbabilityCategory;
use DateTimeImmutable;
use JsonSerializable;

/**
 * Data Transfer Object for updating deal forecast settings.
 */
final readonly class UpdateDealForecastDTO implements JsonSerializable
{
    public function __construct(
        public int $userId,
        public int $moduleRecordId,
        public ?ProbabilityCategory $category = null,
        public ?float $override = null,
        public ?DateTimeImmutable $expectedCloseDate = null,
        public ?string $reason = null,
    ) {}

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) ($data['user_id'] ?? 0),
            moduleRecordId: (int) ($data['module_record_id'] ?? 0),
            category: isset($data['category'])
                ? ProbabilityCategory::from($data['category'])
                : null,
            override: isset($data['override']) ? (float) $data['override'] : null,
            expectedCloseDate: isset($data['expected_close_date'])
                ? ($data['expected_close_date'] instanceof DateTimeImmutable
                    ? $data['expected_close_date']
                    : new DateTimeImmutable($data['expected_close_date']))
                : null,
            reason: $data['reason'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'module_record_id' => $this->moduleRecordId,
            'category' => $this->category?->value,
            'override' => $this->override,
            'expected_close_date' => $this->expectedCloseDate?->format('Y-m-d'),
            'reason' => $this->reason,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
