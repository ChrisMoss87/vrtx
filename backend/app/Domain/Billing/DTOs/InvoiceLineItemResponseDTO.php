<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

use App\Domain\Billing\Entities\InvoiceLineItem;
use JsonSerializable;

/**
 * Data Transfer Object for invoice line item responses.
 */
final readonly class InvoiceLineItemResponseDTO implements JsonSerializable
{
    public function __construct(
        public ?int $id,
        public ?int $productId,
        public string $description,
        public float $quantity,
        public float $unitPrice,
        public float $discountPercent,
        public float $taxRate,
        public float $lineSubtotal,
        public float $lineTax,
        public float $lineTotal,
        public int $displayOrder,
    ) {}

    public static function fromEntity(InvoiceLineItem $lineItem): self
    {
        return new self(
            id: $lineItem->getId(),
            productId: $lineItem->getProductId(),
            description: $lineItem->getDescription(),
            quantity: $lineItem->getQuantity(),
            unitPrice: $lineItem->getUnitPrice()->amount(),
            discountPercent: $lineItem->getDiscountPercent(),
            taxRate: $lineItem->getTaxRate(),
            lineSubtotal: $lineItem->calculateSubtotal()->amount(),
            lineTax: $lineItem->calculateTax()->amount(),
            lineTotal: $lineItem->calculateTotal()->amount(),
            displayOrder: $lineItem->getDisplayOrder(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'discount_percent' => $this->discountPercent,
            'tax_rate' => $this->taxRate,
            'line_subtotal' => $this->lineSubtotal,
            'line_tax' => $this->lineTax,
            'line_total' => $this->lineTotal,
            'display_order' => $this->displayOrder,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
