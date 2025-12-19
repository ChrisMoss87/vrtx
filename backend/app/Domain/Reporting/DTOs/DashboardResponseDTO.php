<?php

declare(strict_types=1);

namespace App\Domain\Reporting\DTOs;

use App\Domain\Reporting\Entities\Dashboard;
use JsonSerializable;

/**
 * Data Transfer Object for dashboard responses.
 */
final readonly class DashboardResponseDTO implements JsonSerializable
{
    /**
     * @param array<WidgetResponseDTO> $widgets
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public ?int $userId,
        public bool $isDefault,
        public bool $isPublic,
        public array $layout,
        public array $settings,
        public array $filters,
        public int $refreshInterval,
        public string $createdAt,
        public ?string $updatedAt,
        public array $widgets = [],
    ) {}

    /**
     * Create from entity.
     */
    public static function fromEntity(Dashboard $dashboard, bool $includeWidgets = false): self
    {
        $widgets = [];
        if ($includeWidgets) {
            $widgets = array_map(
                fn($widget) => WidgetResponseDTO::fromEntity($widget),
                $dashboard->widgets()
            );
        }

        return new self(
            id: $dashboard->getId() ?? 0,
            name: $dashboard->name(),
            description: $dashboard->description(),
            userId: $dashboard->userId()?->value(),
            isDefault: $dashboard->isDefault(),
            isPublic: $dashboard->isPublic(),
            layout: $dashboard->layout(),
            settings: $dashboard->settings(),
            filters: $dashboard->filters(),
            refreshInterval: $dashboard->refreshInterval(),
            createdAt: $dashboard->createdAt()?->toDateTimeString() ?? '',
            updatedAt: $dashboard->updatedAt()?->toDateTimeString(),
            widgets: $widgets,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'user_id' => $this->userId,
            'is_default' => $this->isDefault,
            'is_public' => $this->isPublic,
            'layout' => $this->layout,
            'settings' => $this->settings,
            'filters' => $this->filters,
            'refresh_interval' => $this->refreshInterval,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'widgets' => array_map(fn($w) => $w->toArray(), $this->widgets),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
