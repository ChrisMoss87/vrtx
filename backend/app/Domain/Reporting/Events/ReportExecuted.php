<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Domain event fired when a report is executed.
 */
final readonly class ReportExecuted implements DomainEvent
{
    public function __construct(
        private int $reportId,
        private int $moduleId,
        private string $reportType,
        private bool $usedCache,
        private int $resultCount,
        private float $executionTime,
        private ?int $userId = null,
        private ?DateTimeImmutable $occurredAt = null,
    ) {}

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

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt ?? new DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'report_id' => $this->reportId,
            'module_id' => $this->moduleId,
            'report_type' => $this->reportType,
            'used_cache' => $this->usedCache,
            'result_count' => $this->resultCount,
            'execution_time' => $this->executionTime,
            'user_id' => $this->userId,
            'occurred_at' => $this->occurredAt()->format('Y-m-d H:i:s'),
        ];
    }
}
