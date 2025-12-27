<?php

declare(strict_types=1);

namespace App\Domain\Plugin\Entities;

use App\Domain\Plugin\ValueObjects\Money;
use App\Domain\Plugin\ValueObjects\PluginSlug;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;

final class PluginBundle implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private PluginSlug $slug,
        private string $name,
        private ?string $description,
        /** @var PluginSlug[] */
        private array $plugins,
        private ?Money $priceMonthly,
        private ?Money $priceYearly,
        private int $discountPercent,
        private ?string $icon,
        private int $displayOrder,
        private bool $isActive,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        PluginSlug $slug,
        string $name,
        array $plugins,
        ?Money $priceMonthly = null,
        ?Money $priceYearly = null,
        int $discountPercent = 0,
        ?string $description = null,
        ?string $icon = null,
        int $displayOrder = 0,
    ): self {
        return new self(
            id: null,
            slug: $slug,
            name: $name,
            description: $description,
            plugins: $plugins,
            priceMonthly: $priceMonthly,
            priceYearly: $priceYearly,
            discountPercent: $discountPercent,
            icon: $icon,
            displayOrder: $displayOrder,
            isActive: true,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        PluginSlug $slug,
        string $name,
        ?string $description,
        array $plugins,
        ?Money $priceMonthly,
        ?Money $priceYearly,
        int $discountPercent,
        ?string $icon,
        int $displayOrder,
        bool $isActive,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            slug: $slug,
            name: $name,
            description: $description,
            plugins: $plugins,
            priceMonthly: $priceMonthly,
            priceYearly: $priceYearly,
            discountPercent: $discountPercent,
            icon: $icon,
            displayOrder: $displayOrder,
            isActive: $isActive,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateDetails(
        string $name,
        ?string $description,
        ?string $icon,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->icon = $icon;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updatePlugins(array $plugins): void
    {
        $this->plugins = $plugins;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function addPlugin(PluginSlug $pluginSlug): void
    {
        foreach ($this->plugins as $existing) {
            if ($existing->equals($pluginSlug)) {
                return;
            }
        }

        $this->plugins[] = $pluginSlug;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function removePlugin(PluginSlug $pluginSlug): void
    {
        $this->plugins = array_filter(
            $this->plugins,
            fn(PluginSlug $p) => !$p->equals($pluginSlug)
        );
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updatePricing(
        ?Money $priceMonthly,
        ?Money $priceYearly,
        int $discountPercent = 0,
    ): void {
        $this->priceMonthly = $priceMonthly;
        $this->priceYearly = $priceYearly;
        $this->discountPercent = $discountPercent;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateDisplayOrder(int $order): void
    {
        $this->displayOrder = $order;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function containsPlugin(PluginSlug $pluginSlug): bool
    {
        foreach ($this->plugins as $plugin) {
            if ($plugin->equals($pluginSlug)) {
                return true;
            }
        }
        return false;
    }

    public function pluginCount(): int
    {
        return count($this->plugins);
    }

    public function getSavingsAmount(): ?Money
    {
        if ($this->priceMonthly === null || $this->discountPercent === 0) {
            return null;
        }

        $fullPrice = Money::fromCents(
            (int) ($this->priceMonthly->cents() * 100 / (100 - $this->discountPercent))
        );

        return $fullPrice->subtract($this->priceMonthly);
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): PluginSlug
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return PluginSlug[]
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * @return string[]
     */
    public function getPluginSlugs(): array
    {
        return array_map(fn(PluginSlug $p) => $p->value(), $this->plugins);
    }

    public function getPriceMonthly(): ?Money
    {
        return $this->priceMonthly;
    }

    public function getPriceYearly(): ?Money
    {
        return $this->priceYearly;
    }

    public function getDiscountPercent(): int
    {
        return $this->discountPercent;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug->value(),
            'name' => $this->name,
            'description' => $this->description,
            'plugins' => $this->getPluginSlugs(),
            'price_monthly' => $this->priceMonthly?->amount(),
            'price_yearly' => $this->priceYearly?->amount(),
            'discount_percent' => $this->discountPercent,
            'icon' => $this->icon,
            'display_order' => $this->displayOrder,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'plugin_count' => $this->pluginCount(),
        ];
    }
}
