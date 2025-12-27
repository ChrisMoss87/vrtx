<?php

declare(strict_types=1);

namespace App\Domain\Plugin\Entities;

use App\Domain\Plugin\Events\PluginActivatedEvent;
use App\Domain\Plugin\Events\PluginDeactivatedEvent;
use App\Domain\Plugin\ValueObjects\Money;
use App\Domain\Plugin\ValueObjects\PluginCategory;
use App\Domain\Plugin\ValueObjects\PluginId;
use App\Domain\Plugin\ValueObjects\PluginSlug;
use App\Domain\Plugin\ValueObjects\PluginTier;
use App\Domain\Plugin\ValueObjects\PricingModel;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;

final class Plugin implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?PluginId $id,
        private PluginSlug $slug,
        private string $name,
        private ?string $description,
        private PluginCategory $category,
        private PluginTier $tier,
        private PricingModel $pricingModel,
        private ?Money $priceMonthly,
        private ?Money $priceYearly,
        private array $features,
        private array $requirements,
        private array $limits,
        private ?string $icon,
        private int $displayOrder,
        private bool $isActive,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        PluginSlug $slug,
        string $name,
        PluginCategory $category,
        PluginTier $tier,
        PricingModel $pricingModel,
        ?string $description = null,
        ?Money $priceMonthly = null,
        ?Money $priceYearly = null,
        array $features = [],
        array $requirements = [],
        array $limits = [],
        ?string $icon = null,
        int $displayOrder = 0,
    ): self {
        $plugin = new self(
            id: null,
            slug: $slug,
            name: $name,
            description: $description,
            category: $category,
            tier: $tier,
            pricingModel: $pricingModel,
            priceMonthly: $priceMonthly,
            priceYearly: $priceYearly,
            features: $features,
            requirements: $requirements,
            limits: $limits,
            icon: $icon,
            displayOrder: $displayOrder,
            isActive: true,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );

        return $plugin;
    }

    public static function reconstitute(
        PluginId $id,
        PluginSlug $slug,
        string $name,
        ?string $description,
        PluginCategory $category,
        PluginTier $tier,
        PricingModel $pricingModel,
        ?Money $priceMonthly,
        ?Money $priceYearly,
        array $features,
        array $requirements,
        array $limits,
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
            category: $category,
            tier: $tier,
            pricingModel: $pricingModel,
            priceMonthly: $priceMonthly,
            priceYearly: $priceYearly,
            features: $features,
            requirements: $requirements,
            limits: $limits,
            icon: $icon,
            displayOrder: $displayOrder,
            isActive: $isActive,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function activate(): void
    {
        if ($this->isActive) {
            return;
        }

        $this->isActive = true;
        $this->updatedAt = new DateTimeImmutable();

        $this->raise(new PluginActivatedEvent($this->slug));
    }

    public function deactivate(): void
    {
        if (!$this->isActive) {
            return;
        }

        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable();

        $this->raise(new PluginDeactivatedEvent($this->slug));
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

    public function updatePricing(
        PricingModel $pricingModel,
        ?Money $priceMonthly,
        ?Money $priceYearly,
    ): void {
        $this->pricingModel = $pricingModel;
        $this->priceMonthly = $priceMonthly;
        $this->priceYearly = $priceYearly;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateFeatures(array $features): void
    {
        $this->features = $features;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateRequirements(array $requirements): void
    {
        $this->requirements = $requirements;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateLimits(array $limits): void
    {
        $this->limits = $limits;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateDisplayOrder(int $order): void
    {
        $this->displayOrder = $order;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isFree(): bool
    {
        return $this->pricingModel->isIncluded()
            || ($this->priceMonthly === null || $this->priceMonthly->isZero());
    }

    public function requiresPlan(PluginTier $minimumTier): bool
    {
        return $this->tier->isAtLeast($minimumTier);
    }

    public function hasRequirements(): bool
    {
        return !empty($this->requirements);
    }

    public function hasLimits(): bool
    {
        return !empty($this->limits);
    }

    public function getLimit(string $key): mixed
    {
        return $this->limits[$key] ?? null;
    }

    // Getters
    public function getId(): ?PluginId
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

    public function getCategory(): PluginCategory
    {
        return $this->category;
    }

    public function getTier(): PluginTier
    {
        return $this->tier;
    }

    public function getPricingModel(): PricingModel
    {
        return $this->pricingModel;
    }

    public function getPriceMonthly(): ?Money
    {
        return $this->priceMonthly;
    }

    public function getPriceYearly(): ?Money
    {
        return $this->priceYearly;
    }

    public function getFeatures(): array
    {
        return $this->features;
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function getLimits(): array
    {
        return $this->limits;
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
            'id' => $this->id?->value(),
            'slug' => $this->slug->value(),
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category->value(),
            'tier' => $this->tier->value(),
            'pricing_model' => $this->pricingModel->value(),
            'price_monthly' => $this->priceMonthly?->amount(),
            'price_yearly' => $this->priceYearly?->amount(),
            'features' => $this->features,
            'requirements' => $this->requirements,
            'limits' => $this->limits,
            'icon' => $this->icon,
            'display_order' => $this->displayOrder,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
