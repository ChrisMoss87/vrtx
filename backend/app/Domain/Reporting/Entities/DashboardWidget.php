<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Entities;

use App\Domain\Reporting\ValueObjects\WidgetType;
use App\Domain\Shared\Contracts\Entity as EntityInterface;

/**
 * DashboardWidget entity.
 *
 * Represents a widget on a dashboard.
 */
final class DashboardWidget implements EntityInterface
{
    private function __construct(
        private ?int $id,
        private int $dashboardId,
        private ?int $reportId,
        private string $title,
        private WidgetType $type,
        private array $config,
        private array $gridPosition,
        private int $refreshInterval,
    ) {}

    /**
     * Create a new dashboard widget.
     */
    public static function create(
        int $dashboardId,
        string $title,
        WidgetType $type,
        ?int $reportId = null,
        array $config = [],
        array $gridPosition = ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
        int $refreshInterval = 0,
    ): self {
        return new self(
            id: null,
            dashboardId: $dashboardId,
            reportId: $reportId,
            title: $title,
            type: $type,
            config: $config,
            gridPosition: $gridPosition,
            refreshInterval: $refreshInterval,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        int $dashboardId,
        ?int $reportId,
        string $title,
        WidgetType $type,
        array $config,
        array $gridPosition,
        int $refreshInterval,
    ): self {
        return new self(
            id: $id,
            dashboardId: $dashboardId,
            reportId: $reportId,
            title: $title,
            type: $type,
            config: $config,
            gridPosition: $gridPosition,
            refreshInterval: $refreshInterval,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Update widget configuration.
     */
    public function update(
        string $title,
        array $config,
        int $refreshInterval,
    ): void {
        $this->title = $title;
        $this->config = $config;
        $this->refreshInterval = $refreshInterval;
    }

    /**
     * Update widget grid position.
     */
    public function updateGridPosition(array $gridPosition): void
    {
        $this->gridPosition = array_merge($this->gridPosition, $gridPosition);
    }

    /**
     * Link to a report.
     */
    public function linkReport(int $reportId): void
    {
        $this->reportId = $reportId;
    }

    /**
     * Unlink from report.
     */
    public function unlinkReport(): void
    {
        $this->reportId = null;
    }

    /**
     * Check if widget requires a report.
     */
    public function requiresReport(): bool
    {
        return $this->type->requiresReport();
    }

    /**
     * Check if widget supports auto-refresh.
     */
    public function supportsRefresh(): bool
    {
        return $this->type->supportsRefresh();
    }

    // ========== Entity Implementation ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function equals(EntityInterface $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->id !== null && $this->id === $other->id;
    }

    // ========== Getters ==========

    public function dashboardId(): int
    {
        return $this->dashboardId;
    }

    public function reportId(): ?int
    {
        return $this->reportId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function type(): WidgetType
    {
        return $this->type;
    }

    public function config(): array
    {
        return $this->config;
    }

    public function gridPosition(): array
    {
        return $this->gridPosition;
    }

    public function refreshInterval(): int
    {
        return $this->refreshInterval;
    }
}
