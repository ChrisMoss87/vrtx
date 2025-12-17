<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Entities;

use App\Domain\Reporting\ValueObjects\WidgetType;
use App\Domain\Shared\Contracts\Entity;

/**
 * Dashboard template widget entity.
 *
 * Represents a widget configuration within a dashboard template.
 * When a dashboard is created from a template, these become actual DashboardWidget entities.
 */
final class DashboardTemplateWidget implements Entity
{
    private function __construct(
        private ?int $id,
        private int $templateId,
        private string $title,
        private WidgetType $type,
        private array $config,
        private array $gridPosition,
        private int $refreshInterval,
    ) {}

    /**
     * Create a new template widget.
     */
    public static function create(
        int $templateId,
        string $title,
        WidgetType $type,
        array $config = [],
        array $gridPosition = ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
        int $refreshInterval = 0,
    ): self {
        return new self(
            id: null,
            templateId: $templateId,
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
        int $templateId,
        string $title,
        WidgetType $type,
        array $config,
        array $gridPosition,
        int $refreshInterval,
    ): self {
        return new self(
            id: $id,
            templateId: $templateId,
            title: $title,
            type: $type,
            config: $config,
            gridPosition: $gridPosition,
            refreshInterval: $refreshInterval,
        );
    }

    /**
     * Convert this template widget to a dashboard widget.
     */
    public function toDashboardWidget(int $dashboardId): DashboardWidget
    {
        return DashboardWidget::create(
            dashboardId: $dashboardId,
            title: $this->title,
            type: $this->type,
            reportId: null,
            config: $this->config,
            gridPosition: $this->gridPosition,
            refreshInterval: $this->refreshInterval,
        );
    }

    // ========== Entity Implementation ==========

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

    public function templateId(): int
    {
        return $this->templateId;
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
