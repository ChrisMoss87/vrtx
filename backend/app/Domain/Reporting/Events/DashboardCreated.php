<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Domain event fired when a dashboard is created.
 */
final readonly class DashboardCreated implements DomainEvent
{
    public function __construct(
        private int $dashboardId,
        private string $dashboardName,
        private bool $isDefault,
        private bool $isPublic,
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

    public function isDefault(): bool
    {
        return $this->isDefault;
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
            'dashboard_id' => $this->dashboardId,
            'dashboard_name' => $this->dashboardName,
            'is_default' => $this->isDefault,
            'is_public' => $this->isPublic,
            'user_id' => $this->userId,
            'occurred_at' => $this->occurredAt()->format('Y-m-d H:i:s'),
        ];
    }
}
