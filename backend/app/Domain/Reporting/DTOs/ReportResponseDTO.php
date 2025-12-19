<?php

declare(strict_types=1);

namespace App\Domain\Reporting\DTOs;

use App\Domain\Reporting\Entities\Report;
use JsonSerializable;

/**
 * Data Transfer Object for report responses.
 */
final readonly class ReportResponseDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public int $moduleId,
        public ?int $userId,
        public string $type,
        public ?string $chartType,
        public bool $isPublic,
        public bool $isFavorite,
        public array $config,
        public array $filters,
        public array $grouping,
        public array $aggregations,
        public array $sorting,
        public ?array $dateRange,
        public ?array $schedule,
        public ?string $lastRunAt,
        public ?array $cachedResult,
        public ?string $cacheExpiresAt,
        public string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * Create from entity.
     */
    public static function fromEntity(Report $report): self
    {
        return new self(
            id: $report->getId() ?? 0,
            name: $report->name(),
            description: $report->description(),
            moduleId: $report->moduleId(),
            userId: $report->userId()?->value(),
            type: $report->type()->value,
            chartType: $report->chartType()?->value,
            isPublic: $report->isPublic(),
            isFavorite: $report->isFavorite(),
            config: $report->config(),
            filters: $report->filters(),
            grouping: $report->grouping(),
            aggregations: $report->aggregations(),
            sorting: $report->sorting(),
            dateRange: $report->dateRange()?->toArray(),
            schedule: $report->schedule(),
            lastRunAt: $report->lastRunAt()?->toDateTimeString(),
            cachedResult: $report->cachedResult(),
            cacheExpiresAt: $report->cacheExpiresAt()?->toDateTimeString(),
            createdAt: $report->createdAt()?->toDateTimeString() ?? '',
            updatedAt: $report->updatedAt()?->toDateTimeString(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'module_id' => $this->moduleId,
            'user_id' => $this->userId,
            'type' => $this->type,
            'chart_type' => $this->chartType,
            'is_public' => $this->isPublic,
            'is_favorite' => $this->isFavorite,
            'config' => $this->config,
            'filters' => $this->filters,
            'grouping' => $this->grouping,
            'aggregations' => $this->aggregations,
            'sorting' => $this->sorting,
            'date_range' => $this->dateRange,
            'schedule' => $this->schedule,
            'last_run_at' => $this->lastRunAt,
            'cached_result' => $this->cachedResult,
            'cache_expires_at' => $this->cacheExpiresAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
