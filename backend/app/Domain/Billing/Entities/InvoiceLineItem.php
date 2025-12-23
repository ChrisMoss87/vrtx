<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entities;

use App\Domain\Billing\ValueObjects\Money;
use App\Domain\Shared\Contracts\Entity;

/**
 * Invoice line item entity.
 */
final class InvoiceLineItem implements Entity
{
    private function __construct(
        private ?int $id,
        private int $invoiceId,
        private ?int $productId,
        private string $description,
        private float $quantity,
        private Money $unitPrice,
        private float $discountPercent,
        private float $taxRate,
        private int $displayOrder,
    ) {}

    public static function create(
        int $invoiceId,
        string $description,
        float $quantity,
        Money $unitPrice,
        ?int $productId = null,
        float $discountPercent = 0.0,
        float $taxRate = 0.0,
        int $displayOrder = 0,
    ): self {
        return new self(
            id: null,
            invoiceId: $invoiceId,
            productId: $productId,
            description: $description,
            quantity: $quantity,
            unitPrice: $unitPrice,
            discountPercent: $discountPercent,
            taxRate: $taxRate,
            displayOrder: $displayOrder,
        );
    }

    public static function reconstitute(
        int $id,
        int $invoiceId,
        ?int $productId,
        string $description,
        float $quantity,
        Money $unitPrice,
        float $discountPercent,
        float $taxRate,
        int $displayOrder,
    ): self {
        return new self(
            id: $id,
            invoiceId: $invoiceId,
            productId: $productId,
            description: $description,
            quantity: $quantity,
            unitPrice: $unitPrice,
            discountPercent: $discountPercent,
            taxRate: $taxRate,
            displayOrder: $displayOrder,
        );
    }

    // ========== Business Logic ==========

    /**
     * Calculate the line subtotal (before tax).
     */
    public function calculateSubtotal(): Money
    {
        $baseAmount = $this->unitPrice->multiply($this->quantity);

        if ($this->discountPercent > 0) {
            $discountAmount = $baseAmount->multiply($this->discountPercent / 100);
            return $baseAmount->subtract($discountAmount);
        }

        return $baseAmount;
    }

    /**
     * Calculate the tax amount for this line item.
     */
    public function calculateTax(): Money
    {
        if ($this->taxRate <= 0) {
            return Money::zero($this->unitPrice->currency());
        }

        $subtotal = $this->calculateSubtotal();
        return $subtotal->multiply($this->taxRate / 100);
    }

    /**
     * Calculate the total for this line item (subtotal + tax).
     */
    public function calculateTotal(): Money
    {
        return $this->calculateSubtotal()->add($this->calculateTax());
    }

    // ========== Getters ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoiceId(): int
    {
        return $this->invoiceId;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getUnitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function getDiscountPercent(): float
    {
        return $this->discountPercent;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
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
