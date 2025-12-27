<?php

declare(strict_types=1);

namespace App\Domain\Plugin\Entities;

use App\Domain\Plugin\Events\LicenseActivatedEvent;
use App\Domain\Plugin\Events\LicenseCancelledEvent;
use App\Domain\Plugin\Events\LicenseExpiredEvent;
use App\Domain\Plugin\Events\LicenseReactivatedEvent;
use App\Domain\Plugin\ValueObjects\LicenseStatus;
use App\Domain\Plugin\ValueObjects\Money;
use App\Domain\Plugin\ValueObjects\PluginSlug;
use App\Domain\Plugin\ValueObjects\PricingModel;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;
use InvalidArgumentException;

final class PluginLicense implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private PluginSlug $pluginSlug,
        private ?PluginSlug $bundleSlug,
        private LicenseStatus $status,
        private PricingModel $pricingModel,
        private int $quantity,
        private ?Money $priceMonthly,
        private ?string $externalSubscriptionItemId,
        private ?DateTimeImmutable $activatedAt,
        private ?DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        PluginSlug $pluginSlug,
        PricingModel $pricingModel,
        int $quantity = 1,
        ?Money $priceMonthly = null,
        ?PluginSlug $bundleSlug = null,
        ?string $externalSubscriptionItemId = null,
        ?DateTimeImmutable $expiresAt = null,
    ): self {
        $license = new self(
            id: null,
            pluginSlug: $pluginSlug,
            bundleSlug: $bundleSlug,
            status: LicenseStatus::active(),
            pricingModel: $pricingModel,
            quantity: $quantity,
            priceMonthly: $priceMonthly,
            externalSubscriptionItemId: $externalSubscriptionItemId,
            activatedAt: new DateTimeImmutable(),
            expiresAt: $expiresAt,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );

        $license->raise(new LicenseActivatedEvent($pluginSlug));

        return $license;
    }

    public static function reconstitute(
        int $id,
        PluginSlug $pluginSlug,
        ?PluginSlug $bundleSlug,
        LicenseStatus $status,
        PricingModel $pricingModel,
        int $quantity,
        ?Money $priceMonthly,
        ?string $externalSubscriptionItemId,
        ?DateTimeImmutable $activatedAt,
        ?DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            pluginSlug: $pluginSlug,
            bundleSlug: $bundleSlug,
            status: $status,
            pricingModel: $pricingModel,
            quantity: $quantity,
            priceMonthly: $priceMonthly,
            externalSubscriptionItemId: $externalSubscriptionItemId,
            activatedAt: $activatedAt,
            expiresAt: $expiresAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function cancel(): void
    {
        if ($this->status->isCancelled()) {
            return;
        }

        $this->status = LicenseStatus::cancelled();
        $this->updatedAt = new DateTimeImmutable();

        $this->raise(new LicenseCancelledEvent($this->pluginSlug));
    }

    public function expire(): void
    {
        if ($this->status->isExpired()) {
            return;
        }

        $this->status = LicenseStatus::expired();
        $this->updatedAt = new DateTimeImmutable();

        $this->raise(new LicenseExpiredEvent($this->pluginSlug));
    }

    public function reactivate(?DateTimeImmutable $newExpiresAt = null): void
    {
        if (!$this->status->canBeReactivated()) {
            return;
        }

        $this->status = LicenseStatus::active();
        $this->activatedAt = new DateTimeImmutable();
        $this->expiresAt = $newExpiresAt;
        $this->updatedAt = new DateTimeImmutable();

        $this->raise(new LicenseReactivatedEvent($this->pluginSlug));
    }

    public function updateQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1');
        }

        $this->quantity = $quantity;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateExternalSubscriptionItemId(?string $itemId): void
    {
        $this->externalSubscriptionItemId = $itemId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function extendExpiration(DateTimeImmutable $newExpiresAt): void
    {
        $this->expiresAt = $newExpiresAt;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isValid(): bool
    {
        if (!$this->status->isActive()) {
            return false;
        }

        if ($this->expiresAt !== null && $this->expiresAt < new DateTimeImmutable()) {
            return false;
        }

        return true;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        $threshold = (new DateTimeImmutable())->modify("+{$days} days");
        return $this->expiresAt <= $threshold && $this->expiresAt > new DateTimeImmutable();
    }

    public function daysUntilExpiry(): ?int
    {
        if ($this->expiresAt === null) {
            return null;
        }

        $now = new DateTimeImmutable();
        $diff = $now->diff($this->expiresAt);

        return $diff->invert ? -$diff->days : $diff->days;
    }

    public function isFromBundle(): bool
    {
        return $this->bundleSlug !== null;
    }

    public function calculateMonthlyTotal(): ?Money
    {
        if ($this->priceMonthly === null) {
            return null;
        }

        if ($this->pricingModel->isPerUser()) {
            return $this->priceMonthly->multiply($this->quantity);
        }

        return $this->priceMonthly;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPluginSlug(): PluginSlug
    {
        return $this->pluginSlug;
    }

    public function getBundleSlug(): ?PluginSlug
    {
        return $this->bundleSlug;
    }

    public function getStatus(): LicenseStatus
    {
        return $this->status;
    }

    public function getPricingModel(): PricingModel
    {
        return $this->pricingModel;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getPriceMonthly(): ?Money
    {
        return $this->priceMonthly;
    }

    public function getExternalSubscriptionItemId(): ?string
    {
        return $this->externalSubscriptionItemId;
    }

    public function getActivatedAt(): ?DateTimeImmutable
    {
        return $this->activatedAt;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
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
            'plugin_slug' => $this->pluginSlug->value(),
            'bundle_slug' => $this->bundleSlug?->value(),
            'status' => $this->status->value(),
            'pricing_model' => $this->pricingModel->value(),
            'quantity' => $this->quantity,
            'price_monthly' => $this->priceMonthly?->amount(),
            'external_subscription_item_id' => $this->externalSubscriptionItemId,
            'activated_at' => $this->activatedAt?->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'is_valid' => $this->isValid(),
            'days_until_expiry' => $this->daysUntilExpiry(),
        ];
    }
}
