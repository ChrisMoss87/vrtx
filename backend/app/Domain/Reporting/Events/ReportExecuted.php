<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Domain event fired when a report is executed.
 */
final class ReportExecuted extends DomainEvent
{
    public function __construct(
        private readonly int $reportId,
        private readonly int $moduleId,
        private readonly string $reportType,
        private readonly bool $usedCache,
        private readonly int $resultCount,
        private readonly float $executionTime,
        private readonly ?int $userId = null,
    ) {
        parent::__construct();
    }

    public function reportId(): int
    {
        return $this->reportId;
    }

    public function moduleId(): int
    {
        return $this->moduleId;
    }

    public function reportType(): string
    {
        return $this->reportType;
    }

    public function usedCache(): bool
    {
        return $this->usedCache;
    }

    public function resultCount(): int
    {
        return $this->resultCount;
    }

    public function executionTime(): float
    {
        return $this->executionTime;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function aggregateId(): int|string
    {
        return $this->reportId;
    }

    public function aggregateType(): string
    {
        return 'Report';
    }

    public function toPayload(): array
    {
        return [
            'report_id' => $this->reportId,
            'module_id' => $this->moduleId,
            'report_type' => $this->reportType,
            'used_cache' => $this->usedCache,
            'result_count' => $this->resultCount,
            'execution_time' => $this->executionTime,
            'user_id' => $this->userId,
        ];
    }
}
