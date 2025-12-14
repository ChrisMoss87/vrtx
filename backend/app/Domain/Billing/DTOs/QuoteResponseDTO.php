<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

use App\Domain\Billing\Entities\Quote;
use JsonSerializable;

/**
 * Data Transfer Object for quote responses.
 */
final readonly class QuoteResponseDTO implements JsonSerializable
{
    /**
     * @param array<QuoteLineItemResponseDTO> $lineItems
     */
    public function __construct(
        public int $id,
        public string $quoteNumber,
        public ?int $dealId,
        public ?int $contactId,
        public ?int $companyId,
        public string $status,
        public ?string $title,
        public float $subtotal,
        public float $discountAmount,
        public string $discountType,
        public float $discountPercent,
        public float $taxAmount,
        public float $total,
        public string $currency,
        public ?string $validUntil,
        public ?string $terms,
        public ?string $notes,
        public ?string $internalNotes,
        public ?int $templateId,
        public int $version,
        public string $viewToken,
        public ?string $acceptedAt,
        public ?string $acceptedBy,
        public ?string $rejectedAt,
        public ?string $rejectedBy,
        public ?string $rejectionReason,
        public ?string $viewedAt,
        public ?string $sentAt,
        public ?string $sentToEmail,
        public ?int $createdBy,
        public ?int $assignedTo,
        public string $createdAt,
        public ?string $updatedAt,
        public array $lineItems,
        public string $publicUrl,
    ) {}

    public static function fromEntity(Quote $quote): self
    {
        return new self(
            id: $quote->getId(),
            quoteNumber: $quote->getQuoteNumber(),
            dealId: $quote->getDealId(),
            contactId: $quote->getContactId(),
            companyId: $quote->getCompanyId(),
            status: $quote->getStatus()->value,
            title: $quote->getTitle(),
            subtotal: $quote->getSubtotal()->amount(),
            discountAmount: $quote->getDiscountAmount()->amount(),
            discountType: $quote->getDiscountType()->value,
            discountPercent: $quote->getDiscountPercent(),
            taxAmount: $quote->getTaxAmount()->amount(),
            total: $quote->getTotal()->amount(),
            currency: $quote->getCurrency(),
            validUntil: $quote->getValidUntil()?->format('Y-m-d'),
            terms: $quote->getTerms(),
            notes: $quote->getNotes(),
            internalNotes: $quote->getInternalNotes(),
            templateId: $quote->getTemplateId(),
            version: $quote->getVersion(),
            viewToken: $quote->getViewToken(),
            acceptedAt: $quote->getAcceptedAt()?->format('Y-m-d H:i:s'),
            acceptedBy: $quote->getAcceptedBy(),
            rejectedAt: $quote->getRejectedAt()?->format('Y-m-d H:i:s'),
            rejectedBy: $quote->getRejectedBy(),
            rejectionReason: $quote->getRejectionReason(),
            viewedAt: $quote->getViewedAt()?->format('Y-m-d H:i:s'),
            sentAt: $quote->getSentAt()?->format('Y-m-d H:i:s'),
            sentToEmail: $quote->getSentToEmail(),
            createdBy: $quote->getCreatedBy(),
            assignedTo: $quote->getAssignedTo(),
            createdAt: $quote->getCreatedAt()->format('Y-m-d H:i:s'),
            updatedAt: $quote->getUpdatedAt()?->format('Y-m-d H:i:s'),
            lineItems: array_map(
                fn($item) => QuoteLineItemResponseDTO::fromEntity($item),
                $quote->getLineItems()
            ),
            publicUrl: $quote->getPublicUrl(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'quote_number' => $this->quoteNumber,
            'deal_id' => $this->dealId,
            'contact_id' => $this->contactId,
            'company_id' => $this->companyId,
            'status' => $this->status,
            'title' => $this->title,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discountAmount,
            'discount_type' => $this->discountType,
            'discount_percent' => $this->discountPercent,
            'tax_amount' => $this->taxAmount,
            'total' => $this->total,
            'currency' => $this->currency,
            'valid_until' => $this->validUntil,
            'terms' => $this->terms,
            'notes' => $this->notes,
            'internal_notes' => $this->internalNotes,
            'template_id' => $this->templateId,
            'version' => $this->version,
            'view_token' => $this->viewToken,
            'accepted_at' => $this->acceptedAt,
            'accepted_by' => $this->acceptedBy,
            'rejected_at' => $this->rejectedAt,
            'rejected_by' => $this->rejectedBy,
            'rejection_reason' => $this->rejectionReason,
            'viewed_at' => $this->viewedAt,
            'sent_at' => $this->sentAt,
            'sent_to_email' => $this->sentToEmail,
            'created_by' => $this->createdBy,
            'assigned_to' => $this->assignedTo,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'line_items' => array_map(fn($item) => $item->toArray(), $this->lineItems),
            'public_url' => $this->publicUrl,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
