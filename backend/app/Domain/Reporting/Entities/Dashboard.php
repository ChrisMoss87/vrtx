<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Entities;

use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;

/**
 * Dashboard aggregate root entity.
 *
 * Represents a customizable dashboard containing multiple widgets.
 */
final class Dashboard implements AggregateRoot
{
    use HasDomainEvents;

    /** @var array<DashboardWidget> */
    private array $widgets = [];

    private function __construct(
        private ?int $id,
        private string $name,
        private ?string $description,
        private ?UserId $userId,
        private bool $isDefault,
        private bool $isPublic,
        private array $layout,
        private array $settings,
        private array $filters,
        private int $refreshInterval,
        private ?Timestamp $createdAt,
        private ?Timestamp $updatedAt,
        private ?Timestamp $deletedAt,
    ) {}

    /**
     * Create a new dashboard.
     */
    public static function create(
        string $name,
        ?UserId $userId = null,
        ?string $description = null,
        bool $isDefault = false,
        bool $isPublic = false,
    ): self {
        return new self(
            id: null,
            name: $name,
            description: $description,
            userId: $userId,
            isDefault: $isDefault,
            isPublic: $isPublic,
            layout: [],
            settings: [],
            filters: [],
            refreshInterval: 0,
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
        ?UserId $userId,
        bool $isDefault,
        bool $isPublic,
        array $layout,
        array $settings,
        array $filters,
        int $refreshInterval,
        ?Timestamp $createdAt,
        ?Timestamp $updatedAt,
        ?Timestamp $deletedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            description: $description,
            userId: $userId,
            isDefault: $isDefault,
            isPublic: $isPublic,
            layout: $layout,
            settings: $settings,
            filters: $filters,
            refreshInterval: $refreshInterval,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Update dashboard details.
     */
    public function update(
        string $name,
        ?string $description,
        array $settings,
        array $filters,
        int $refreshInterval,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->settings = $settings;
        $this->filters = $filters;
        $this->refreshInterval = $refreshInterval;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Update dashboard layout.
     */
    public function updateLayout(array $layout): void
    {
        $this->layout = $layout;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Update widget position in layout.
     */
    public function updateWidgetPosition(int $widgetId, array $position, string $breakpoint = 'lg'): void
    {
        if (!isset($this->layout[$breakpoint])) {
            $this->layout[$breakpoint] = [];
        }

        $found = false;
        foreach ($this->layout[$breakpoint] as $key => $item) {
            if (isset($item['i']) && $item['i'] === $widgetId) {
                $this->layout[$breakpoint][$key] = array_merge($item, $position);
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->layout[$breakpoint][] = array_merge(['i' => $widgetId], $position);
        }

        $this->updatedAt = Timestamp::now();
    }

    /**
     * Make dashboard public.
     */
    public function makePublic(): void
    {
        $this->isPublic = true;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Make dashboard private.
     */
    public function makePrivate(): void
    {
        $this->isPublic = false;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Set as default dashboard for user.
     */
    public function setAsDefault(): void
    {
        $this->isDefault = true;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Unset as default dashboard.
     */
    public function unsetAsDefault(): void
    {
        $this->isDefault = false;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Add a widget to the dashboard.
     */
    public function addWidget(DashboardWidget $widget): void
    {
        $this->widgets[] = $widget;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Set all widgets at once.
     *
     * @param array<DashboardWidget> $widgets
     */
    public function setWidgets(array $widgets): void
    {
        $this->widgets = $widgets;
    }

    /**
     * Remove a widget from the dashboard.
     */
    public function removeWidget(int $widgetId): void
    {
        $this->widgets = array_filter(
            $this->widgets,
            fn(DashboardWidget $w) => $w->getId() !== $widgetId
        );
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Get layout for a specific breakpoint.
     */
    public function getLayoutForBreakpoint(string $breakpoint = 'lg'): array
    {
        return $this->layout[$breakpoint] ?? $this->layout['lg'] ?? [];
    }

    /**
     * Soft delete the dashboard.
     */
    public function delete(): void
    {
        $this->deletedAt = Timestamp::now();
    }

    /**
     * Restore a soft-deleted dashboard.
     */
    public function restore(): void
    {
        $this->deletedAt = null;
    }

    /**
     * Check if dashboard is deleted.
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

    public function userId(): ?UserId
    {
        return $this->userId;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function layout(): array
    {
        return $this->layout;
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public function filters(): array
    {
        return $this->filters;
    }

    public function refreshInterval(): int
    {
        return $this->refreshInterval;
    }

    /**
     * @return array<DashboardWidget>
     */
    public function widgets(): array
    {
        return $this->widgets;
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
