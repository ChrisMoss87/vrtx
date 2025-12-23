<?php

declare(strict_types=1);

namespace App\Domain\Integration\DTOs;

/**
 * Normalized invoice line item from external integrations
 */
final readonly class ExternalInvoiceLineDTO
{
    public function __construct(
        public ?string $externalId = null,
        public ?string $description = null,
        public ?float $quantity = null,
        public ?float $unitPrice = null,
        public ?float $amount = null,
        public ?float $taxAmount = null,
        public ?string $taxCode = null,
        public ?string $accountCode = null,
        public ?string $itemCode = null,
        public ?float $discountAmount = null,
        public ?float $discountPercent = null,
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'amount' => $this->amount,
            'tax_amount' => $this->taxAmount,
            'tax_code' => $this->taxCode,
            'account_code' => $this->accountCode,
            'item_code' => $this->itemCode,
            'discount_amount' => $this->discountAmount,
            'discount_percent' => $this->discountPercent,
            'metadata' => $this->metadata,
        ];
    }
}
