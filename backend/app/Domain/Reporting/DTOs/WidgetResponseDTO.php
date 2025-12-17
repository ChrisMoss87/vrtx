<?php

declare(strict_types=1);

namespace App\Domain\Reporting\DTOs;

use App\Domain\Reporting\Entities\DashboardWidget;
use JsonSerializable;

/**
 * Data Transfer Object for widget responses.
 */
final readonly class WidgetResponseDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $dashboardId,
        public ?int $reportId,
        public string $title,
        public string $type,
        public array $config,
        public array $gridPosition,
        public int $refreshInterval,
    ) {}

    /**
     * Create from entity.
     */
    public static function fromEntity(DashboardWidget $widget): self
    {
        return new self(
            id: $widget->getId() ?? 0,
            dashboardId: $widget->dashboardId(),
            reportId: $widget->reportId(),
            title: $widget->title(),
            type: $widget->type()->value,
            config: $widget->config(),
            gridPosition: $widget->gridPosition(),
            refreshInterval: $widget->refreshInterval(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'dashboard_id' => $this->dashboardId,
            'report_id' => $this->reportId,
            'title' => $this->title,
            'type' => $this->type,
            'config' => $this->config,
            'grid_position' => $this->gridPosition,
            'refresh_interval' => $this->refreshInterval,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
