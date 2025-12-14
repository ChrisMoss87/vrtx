<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Data Transfer Object for creating an invoice line item.
 */
final readonly class CreateInvoiceLineItemDTO implements JsonSerializable
{
    public function __construct(
        public string $description,
        public float $quantity,
        public float $unitPrice,
        public ?int $productId = null,
        public float $discountPercent = 0.0,
        public float $taxRate = 0.0,
        public int $displayOrder = 0,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            description: $data['description'] ?? throw new InvalidArgumentException('Description is required'),
            quantity: (float) ($data['quantity'] ?? 1),
            unitPrice: (float) ($data['unit_price'] ?? throw new InvalidArgumentException('Unit price is required')),
            productId: isset($data['product_id']) ? (int) $data['product_id'] : null,
            discountPercent: (float) ($data['discount_percent'] ?? 0.0),
            taxRate: (float) ($data['tax_rate'] ?? 0.0),
            displayOrder: (int) ($data['display_order'] ?? 0),
        );
    }

    private function validate(): void
    {
        if (empty(trim($this->description))) {
            throw new InvalidArgumentException('Description cannot be empty');
        }

        if ($this->quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than 0');
        }

        if ($this->unitPrice < 0) {
            throw new InvalidArgumentException('Unit price cannot be negative');
        }

        if ($this->discountPercent < 0 || $this->discountPercent > 100) {
            throw new InvalidArgumentException('Discount percent must be between 0 and 100');
        }

        if ($this->taxRate < 0 || $this->taxRate > 100) {
            throw new InvalidArgumentException('Tax rate must be between 0 and 100');
        }
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'discount_percent' => $this->discountPercent,
            'tax_rate' => $this->taxRate,
            'display_order' => $this->displayOrder,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
