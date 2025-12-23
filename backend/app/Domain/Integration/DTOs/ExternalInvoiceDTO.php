<?php

declare(strict_types=1);

namespace App\Domain\Integration\DTOs;

/**
 * Normalized invoice data from external integrations
 */
final readonly class ExternalInvoiceDTO
{
    public function __construct(
        public string $externalId,
        public string $provider,
        public string $externalCustomerId,
        public ?string $invoiceNumber = null,
        public ?string $reference = null,
        public ?string $status = null,
        public ?string $currency = null,
        public ?float $subtotal = null,
        public ?float $taxAmount = null,
        public ?float $total = null,
        public ?float $amountDue = null,
        public ?float $amountPaid = null,
        public ?\DateTimeImmutable $invoiceDate = null,
        public ?\DateTimeImmutable $dueDate = null,
        public ?string $terms = null,
        public ?string $notes = null,
        public ?string $privateNotes = null,
        public array $lineItems = [],
        public array $metadata = [],
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'provider' => $this->provider,
            'external_customer_id' => $this->externalCustomerId,
            'invoice_number' => $this->invoiceNumber,
            'reference' => $this->reference,
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->taxAmount,
            'total' => $this->total,
            'amount_due' => $this->amountDue,
            'amount_paid' => $this->amountPaid,
            'invoice_date' => $this->invoiceDate?->format('Y-m-d'),
            'due_date' => $this->dueDate?->format('Y-m-d'),
            'terms' => $this->terms,
            'notes' => $this->notes,
            'private_notes' => $this->privateNotes,
            'line_items' => $this->lineItems,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
