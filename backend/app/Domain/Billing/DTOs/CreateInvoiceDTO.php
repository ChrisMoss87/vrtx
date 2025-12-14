<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

use App\Domain\Billing\ValueObjects\PaymentTerms;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Data Transfer Object for creating a new invoice.
 */
final readonly class CreateInvoiceDTO implements JsonSerializable
{
    /**
     * @param string $currency Currency code (e.g., USD, EUR)
     * @param DateTimeImmutable $issueDate Date invoice was issued
     * @param PaymentTerms $paymentTerms Payment terms
     * @param int|null $quoteId Related quote ID
     * @param int|null $dealId Related deal ID
     * @param int|null $contactId Contact ID
     * @param int|null $companyId Company ID
     * @param string|null $title Invoice title
     * @param string|null $notes Customer-facing notes
     * @param string|null $internalNotes Internal notes
     * @param int|null $templateId Template ID for rendering
     * @param float $discountAmount Fixed discount amount
     * @param int|null $createdBy User creating this invoice
     * @param array<CreateInvoiceLineItemDTO> $lineItems Line items
     */
    public function __construct(
        public string $currency,
        public DateTimeImmutable $issueDate,
        public PaymentTerms $paymentTerms,
        public ?int $quoteId = null,
        public ?int $dealId = null,
        public ?int $contactId = null,
        public ?int $companyId = null,
        public ?string $title = null,
        public ?string $notes = null,
        public ?string $internalNotes = null,
        public ?int $templateId = null,
        public float $discountAmount = 0.0,
        public ?int $createdBy = null,
        public array $lineItems = [],
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            currency: $data['currency'] ?? 'USD',
            issueDate: isset($data['issue_date'])
                ? new DateTimeImmutable($data['issue_date'])
                : new DateTimeImmutable(),
            paymentTerms: isset($data['payment_terms'])
                ? PaymentTerms::from($data['payment_terms'])
                : PaymentTerms::NET_30,
            quoteId: isset($data['quote_id']) ? (int) $data['quote_id'] : null,
            dealId: isset($data['deal_id']) ? (int) $data['deal_id'] : null,
            contactId: isset($data['contact_id']) ? (int) $data['contact_id'] : null,
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null,
            title: $data['title'] ?? null,
            notes: $data['notes'] ?? null,
            internalNotes: $data['internal_notes'] ?? null,
            templateId: isset($data['template_id']) ? (int) $data['template_id'] : null,
            discountAmount: (float) ($data['discount_amount'] ?? 0.0),
            createdBy: isset($data['created_by']) ? (int) $data['created_by'] : null,
            lineItems: isset($data['line_items']) && is_array($data['line_items'])
                ? array_map(fn($item) => CreateInvoiceLineItemDTO::fromArray($item), $data['line_items'])
                : [],
        );
    }

    private function validate(): void
    {
        if (strlen($this->currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a valid 3-letter ISO code');
        }

        if ($this->discountAmount < 0) {
            throw new InvalidArgumentException('Discount amount cannot be negative');
        }

        foreach ($this->lineItems as $lineItem) {
            if (!$lineItem instanceof CreateInvoiceLineItemDTO) {
                throw new InvalidArgumentException('Line items must be CreateInvoiceLineItemDTO instances');
            }
        }
    }

    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'issue_date' => $this->issueDate->format('Y-m-d'),
            'payment_terms' => $this->paymentTerms->value,
            'quote_id' => $this->quoteId,
            'deal_id' => $this->dealId,
            'contact_id' => $this->contactId,
            'company_id' => $this->companyId,
            'title' => $this->title,
            'notes' => $this->notes,
            'internal_notes' => $this->internalNotes,
            'template_id' => $this->templateId,
            'discount_amount' => $this->discountAmount,
            'created_by' => $this->createdBy,
            'line_items' => array_map(fn($item) => $item->toArray(), $this->lineItems),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
