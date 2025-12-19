<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Domain event fired when a report is created.
 */
final readonly class ReportCreated implements DomainEvent
{
    public function __construct(
        private int $reportId,
        private string $reportName,
        private int $moduleId,
        private string $reportType,
        private bool $isPublic,
        private ?int $userId = null,
        private ?DateTimeImmutable $occurredAt = null,
    ) {}

    public function reportId(): int
    {
        return $this->reportId;
    }

    public function reportName(): string
    {
        return $this->reportName;
    }

    public function moduleId(): int
    {
        return $this->moduleId;
    }

    public function reportType(): string
    {
        return $this->reportType;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
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
            'report_name' => $this->reportName,
            'module_id' => $this->moduleId,
            'report_type' => $this->reportType,
            'is_public' => $this->isPublic,
            'user_id' => $this->userId,
            'occurred_at' => $this->occurredAt()->format('Y-m-d H:i:s'),
        ];
    }
}
