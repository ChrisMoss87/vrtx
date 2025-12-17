<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Entities;

use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\ValueObjects\Timestamp;

/**
 * Dashboard template entity.
 *
 * Represents a pre-built dashboard template that can be used to quickly
 * create new dashboards with a predefined widget layout.
 */
final class DashboardTemplate implements Entity
{
    /** @var array<DashboardTemplateWidget> */
    private array $widgets = [];

    private function __construct(
        private ?int $id,
        private string $name,
        private string $slug,
        private ?string $description,
        private string $category,
        private ?string $thumbnail,
        private array $settings,
        private bool $isActive,
        private int $sortOrder,
        private ?Timestamp $createdAt,
        private ?Timestamp $updatedAt,
    ) {}

    /**
     * Create a new dashboard template.
     */
    public static function create(
        string $name,
        string $slug,
        string $category,
        ?string $description = null,
        ?string $thumbnail = null,
        array $settings = [],
        int $sortOrder = 0,
    ): self {
        return new self(
            id: null,
            name: $name,
            slug: $slug,
            description: $description,
            category: $category,
            thumbnail: $thumbnail,
            settings: $settings,
            isActive: true,
            sortOrder: $sortOrder,
            createdAt: Timestamp::now(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        string $name,
        string $slug,
        ?string $description,
        string $category,
        ?string $thumbnail,
        array $settings,
        bool $isActive,
        int $sortOrder,
        ?Timestamp $createdAt,
        ?Timestamp $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            slug: $slug,
            description: $description,
            category: $category,
            thumbnail: $thumbnail,
            settings: $settings,
            isActive: $isActive,
            sortOrder: $sortOrder,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Update template details.
     */
    public function update(
        string $name,
        ?string $description,
        string $category,
        ?string $thumbnail,
        array $settings,
        int $sortOrder,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->category = $category;
        $this->thumbnail = $thumbnail;
        $this->settings = $settings;
        $this->sortOrder = $sortOrder;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Activate the template.
     */
    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Deactivate the template.
     */
    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Add a widget to the template.
     */
    public function addWidget(DashboardTemplateWidget $widget): void
    {
        $this->widgets[] = $widget;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Set all widgets at once.
     *
     * @param array<DashboardTemplateWidget> $widgets
     */
    public function setWidgets(array $widgets): void
    {
        $this->widgets = $widgets;
    }

    /**
     * Remove a widget from the template.
     */
    public function removeWidget(int $widgetId): void
    {
        $this->widgets = array_filter(
            $this->widgets,
            fn(DashboardTemplateWidget $w) => $w->getId() !== $widgetId
        );
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Create a dashboard from this template.
     */
    public function createDashboard(string $name, ?string $description = null): Dashboard
    {
        $dashboard = Dashboard::create(
            name: $name,
            description: $description ?? $this->description,
        );

        return $dashboard;
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

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function thumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @return array<DashboardTemplateWidget>
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
}
