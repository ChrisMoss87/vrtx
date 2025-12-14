<?php

declare(strict_types=1);

namespace App\Domain\Reporting\DTOs;

use App\Domain\Reporting\ValueObjects\ChartType;
use App\Domain\Reporting\ValueObjects\DateRange;
use App\Domain\Reporting\ValueObjects\ReportType;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Data Transfer Object for creating a new report.
 */
final readonly class CreateReportDTO implements JsonSerializable
{
    /**
     * @param string $name Report name
     * @param int $moduleId Module ID this report belongs to
     * @param ReportType $type Report type
     * @param string|null $description Optional description
     * @param ChartType|null $chartType Chart type (required for chart reports)
     * @param int|null $userId User ID who owns this report
     * @param array<mixed> $filters Filters to apply
     * @param array<mixed> $grouping Grouping configuration
     * @param array<mixed> $aggregations Aggregations to calculate
     * @param array<mixed> $sorting Sorting configuration
     * @param DateRange|null $dateRange Date range filter
     * @param array<mixed> $config Additional configuration
     */
    public function __construct(
        public string $name,
        public int $moduleId,
        public ReportType $type,
        public ?string $description = null,
        public ?ChartType $chartType = null,
        public ?int $userId = null,
        public array $filters = [],
        public array $grouping = [],
        public array $aggregations = [],
        public array $sorting = [],
        public ?DateRange $dateRange = null,
        public array $config = [],
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
            moduleId: (int) ($data['module_id'] ?? throw new InvalidArgumentException('Module ID is required')),
            type: isset($data['type'])
                ? ReportType::from($data['type'])
                : throw new InvalidArgumentException('Type is required'),
            description: $data['description'] ?? null,
            chartType: isset($data['chart_type']) ? ChartType::from($data['chart_type']) : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            filters: $data['filters'] ?? [],
            grouping: $data['grouping'] ?? [],
            aggregations: $data['aggregations'] ?? [],
            sorting: $data['sorting'] ?? [],
            dateRange: isset($data['date_range']) ? DateRange::fromArray($data['date_range']) : null,
            config: $data['config'] ?? [],
        );
    }

    /**
     * Validate the DTO.
     */
    private function validate(): void
    {
        if (empty(trim($this->name))) {
            throw new InvalidArgumentException('Report name cannot be empty');
        }

        if (strlen($this->name) > 255) {
            throw new InvalidArgumentException('Report name cannot exceed 255 characters');
        }

        if ($this->moduleId < 1) {
            throw new InvalidArgumentException('Module ID must be a positive integer');
        }

        // Chart type is required for chart reports
        if ($this->type === ReportType::CHART && $this->chartType === null) {
            throw new InvalidArgumentException('Chart type is required for chart reports');
        }

        // Aggregations are required for certain report types
        if ($this->type->requiresAggregations() && empty($this->aggregations)) {
            throw new InvalidArgumentException("Aggregations are required for {$this->type->value} reports");
        }
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'module_id' => $this->moduleId,
            'type' => $this->type->value,
            'description' => $this->description,
            'chart_type' => $this->chartType?->value,
            'user_id' => $this->userId,
            'filters' => $this->filters,
            'grouping' => $this->grouping,
            'aggregations' => $this->aggregations,
            'sorting' => $this->sorting,
            'date_range' => $this->dateRange?->toArray(),
            'config' => $this->config,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
