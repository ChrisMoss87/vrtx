<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Domain event fired when a dashboard is updated.
 */
final readonly class DashboardUpdated implements DomainEvent
{
    public function __construct(
        private int $dashboardId,
        private string $dashboardName,
        private array $changedFields,
        private ?int $userId = null,
        private ?DateTimeImmutable $occurredAt = null,
    ) {}

    public function dashboardId(): int
    {
        return $this->dashboardId;
    }

    public function dashboardName(): string
    {
        return $this->dashboardName;
    }

    public function changedFields(): array
    {
        return $this->changedFields;
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
            'dashboard_id' => $this->dashboardId,
            'dashboard_name' => $this->dashboardName,
            'changed_fields' => $this->changedFields,
            'user_id' => $this->userId,
            'occurred_at' => $this->occurredAt()->format('Y-m-d H:i:s'),
        ];
    }
}
