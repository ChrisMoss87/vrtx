<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entities;

use App\Domain\Billing\ValueObjects\Money;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

/**
 * Product entity.
 *
 * Represents a product or service that can be sold.
 */
final class Product implements Entity
{
    private function __construct(
        private ?int $id,
        private string $name,
        private ?string $sku,
        private ?string $description,
        private Money $unitPrice,
        private string $currency,
        private float $taxRate,
        private bool $isActive,
        private ?int $categoryId,
        private string $unit,
        private array $settings,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        string $name,
        Money $unitPrice,
        ?string $sku = null,
        ?string $description = null,
        float $taxRate = 0.0,
        ?int $categoryId = null,
        string $unit = 'unit',
        array $settings = [],
    ): self {
        return new self(
            id: null,
            name: $name,
            sku: $sku,
            description: $description,
            unitPrice: $unitPrice,
            currency: $unitPrice->currency(),
            taxRate: $taxRate,
            isActive: true,
            categoryId: $categoryId,
            unit: $unit,
            settings: $settings,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        ?string $sku,
        ?string $description,
        Money $unitPrice,
        string $currency,
        float $taxRate,
        bool $isActive,
        ?int $categoryId,
        string $unit,
        array $settings,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            sku: $sku,
            description: $description,
            unitPrice: $unitPrice,
            currency: $currency,
            taxRate: $taxRate,
            isActive: $isActive,
            categoryId: $categoryId,
            unit: $unit,
            settings: $settings,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    public function update(
        ?string $name = null,
        ?string $sku = null,
        ?string $description = null,
        ?Money $unitPrice = null,
        ?float $taxRate = null,
        ?int $categoryId = null,
        ?string $unit = null,
        ?array $settings = null,
    ): void {
        if ($name !== null) {
            $this->name = $name;
        }

        if ($sku !== null) {
            $this->sku = $sku;
        }

        if ($description !== null) {
            $this->description = $description;
        }

        if ($unitPrice !== null) {
            $this->unitPrice = $unitPrice;
            $this->currency = $unitPrice->currency();
        }

        if ($taxRate !== null) {
            $this->taxRate = $taxRate;
        }

        if ($categoryId !== null) {
            $this->categoryId = $categoryId;
        }

        if ($unit !== null) {
            $this->unit = $unit;
        }

        if ($settings !== null) {
            $this->settings = $settings;
        }

        $this->updatedAt = new DateTimeImmutable();
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

    // ========== Getters ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getUnitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        if ($this->id === null || $other->getId() === null) {
            return false;
        }

        return $this->id === $other->getId();
    }
}
