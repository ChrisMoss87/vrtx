<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Entities;

use App\Domain\Reporting\ValueObjects\ChartType;
use App\Domain\Reporting\ValueObjects\DateRange;
use App\Domain\Reporting\ValueObjects\ReportType;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;

/**
 * Report aggregate root entity.
 *
 * Represents a saved report configuration that can be executed
 * to generate analytics and insights.
 */
final class Report implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private string $name,
        private ?string $description,
        private int $moduleId,
        private ?UserId $userId,
        private ReportType $type,
        private ?ChartType $chartType,
        private bool $isPublic,
        private bool $isFavorite,
        private array $config,
        private array $filters,
        private array $grouping,
        private array $aggregations,
        private array $sorting,
        private ?DateRange $dateRange,
        private ?array $schedule,
        private ?Timestamp $lastRunAt,
        private ?array $cachedResult,
        private ?Timestamp $cacheExpiresAt,
        private ?Timestamp $createdAt,
        private ?Timestamp $updatedAt,
        private ?Timestamp $deletedAt,
    ) {}

    /**
     * Create a new report.
     */
    public static function create(
        string $name,
        int $moduleId,
        ReportType $type,
        ?UserId $userId = null,
        ?string $description = null,
        ?ChartType $chartType = null,
        array $filters = [],
        array $grouping = [],
        array $aggregations = [],
        array $sorting = [],
        ?DateRange $dateRange = null,
    ): self {
        return new self(
            id: null,
            name: $name,
            description: $description,
            moduleId: $moduleId,
            userId: $userId,
            type: $type,
            chartType: $chartType,
            isPublic: false,
            isFavorite: false,
            config: [],
            filters: $filters,
            grouping: $grouping,
            aggregations: $aggregations,
            sorting: $sorting,
            dateRange: $dateRange,
            schedule: null,
            lastRunAt: null,
            cachedResult: null,
            cacheExpiresAt: null,
            createdAt: Timestamp::now(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        string $name,
        ?string $description,
        int $moduleId,
        ?UserId $userId,
        ReportType $type,
        ?ChartType $chartType,
        bool $isPublic,
        bool $isFavorite,
        array $config,
        array $filters,
        array $grouping,
        array $aggregations,
        array $sorting,
        ?DateRange $dateRange,
        ?array $schedule,
        ?Timestamp $lastRunAt,
        ?array $cachedResult,
        ?Timestamp $cacheExpiresAt,
        ?Timestamp $createdAt,
        ?Timestamp $updatedAt,
        ?Timestamp $deletedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            description: $description,
            moduleId: $moduleId,
            userId: $userId,
            type: $type,
            chartType: $chartType,
            isPublic: $isPublic,
            isFavorite: $isFavorite,
            config: $config,
            filters: $filters,
            grouping: $grouping,
            aggregations: $aggregations,
            sorting: $sorting,
            dateRange: $dateRange,
            schedule: $schedule,
            lastRunAt: $lastRunAt,
            cachedResult: $cachedResult,
            cacheExpiresAt: $cacheExpiresAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Update report configuration.
     */
    public function update(
        string $name,
        ?string $description,
        ReportType $type,
        ?ChartType $chartType,
        array $filters,
        array $grouping,
        array $aggregations,
        array $sorting,
        ?DateRange $dateRange,
        array $config,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->type = $type;
        $this->chartType = $chartType;
        $this->filters = $filters;
        $this->grouping = $grouping;
        $this->aggregations = $aggregations;
        $this->sorting = $sorting;
        $this->dateRange = $dateRange;
        $this->config = $config;
        $this->updatedAt = Timestamp::now();

        // Clear cache when report is updated
        $this->clearCache();
    }

    /**
     * Make report public.
     */
    public function makePublic(): void
    {
        $this->isPublic = true;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Make report private.
     */
    public function makePrivate(): void
    {
        $this->isPublic = false;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Mark as favorite.
     */
    public function markAsFavorite(): void
    {
        $this->isFavorite = true;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Unmark as favorite.
     */
    public function unmarkAsFavorite(): void
    {
        $this->isFavorite = false;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Configure report scheduling.
     */
    public function configureSchedule(?array $schedule): void
    {
        $this->schedule = $schedule;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Update cache with execution result.
     */
    public function updateCache(array $result, int $ttlMinutes = 15): void
    {
        $this->cachedResult = $result;
        $this->cacheExpiresAt = Timestamp::fromDateTime(
            (new \DateTimeImmutable())->modify("+{$ttlMinutes} minutes")
        );
        $this->lastRunAt = Timestamp::now();
    }

    /**
     * Clear cached result.
     */
    public function clearCache(): void
    {
        $this->cachedResult = null;
        $this->cacheExpiresAt = null;
    }

    /**
     * Check if cache is valid.
     */
    public function isCacheValid(): bool
    {
        if ($this->cachedResult === null || $this->cacheExpiresAt === null) {
            return false;
        }

        return $this->cacheExpiresAt->isAfter(Timestamp::now());
    }

    /**
     * Soft delete the report.
     */
    public function delete(): void
    {
        $this->deletedAt = Timestamp::now();
    }

    /**
     * Restore a soft-deleted report.
     */
    public function restore(): void
    {
        $this->deletedAt = null;
    }

    /**
     * Check if report is deleted.
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    // ========== AggregateRoot Implementation ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function equals(Entity $other): bool
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

    public function moduleId(): int
    {
        return $this->moduleId;
    }

    public function userId(): ?UserId
    {
        return $this->userId;
    }

    public function type(): ReportType
    {
        return $this->type;
    }

    public function chartType(): ?ChartType
    {
        return $this->chartType;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function isFavorite(): bool
    {
        return $this->isFavorite;
    }

    public function config(): array
    {
        return $this->config;
    }

    public function filters(): array
    {
        return $this->filters;
    }

    public function grouping(): array
    {
        return $this->grouping;
    }

    public function aggregations(): array
    {
        return $this->aggregations;
    }

    public function sorting(): array
    {
        return $this->sorting;
    }

    public function dateRange(): ?DateRange
    {
        return $this->dateRange;
    }

    public function schedule(): ?array
    {
        return $this->schedule;
    }

    public function lastRunAt(): ?Timestamp
    {
        return $this->lastRunAt;
    }

    public function cachedResult(): ?array
    {
        return $this->cachedResult;
    }

    public function cacheExpiresAt(): ?Timestamp
    {
        return $this->cacheExpiresAt;
    }

    public function createdAt(): ?Timestamp
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?Timestamp
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?Timestamp
    {
        return $this->deletedAt;
    }
}
