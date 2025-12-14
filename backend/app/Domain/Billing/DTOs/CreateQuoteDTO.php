<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

use App\Domain\Billing\ValueObjects\DiscountType;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Data Transfer Object for creating a new quote.
 */
final readonly class CreateQuoteDTO implements JsonSerializable
{
    /**
     * @param string $currency Currency code (e.g., USD, EUR)
     * @param int|null $dealId Related deal ID
     * @param int|null $contactId Contact ID
     * @param int|null $companyId Company ID
     * @param string|null $title Quote title
     * @param DateTimeImmutable|null $validUntil Expiration date
     * @param string|null $terms Terms and conditions
     * @param string|null $notes Customer-facing notes
     * @param string|null $internalNotes Internal notes
     * @param int|null $templateId Template ID for rendering
     * @param DiscountType $discountType Type of discount
     * @param float $discountAmount Fixed discount amount
     * @param float $discountPercent Percentage discount
     * @param int|null $assignedTo User assigned to this quote
     * @param int|null $createdBy User creating this quote
     * @param array<CreateQuoteLineItemDTO> $lineItems Line items
     */
    public function __construct(
        public string $currency,
        public ?int $dealId = null,
        public ?int $contactId = null,
        public ?int $companyId = null,
        public ?string $title = null,
        public ?DateTimeImmutable $validUntil = null,
        public ?string $terms = null,
        public ?string $notes = null,
        public ?string $internalNotes = null,
        public ?int $templateId = null,
        public DiscountType $discountType = DiscountType::FIXED,
        public float $discountAmount = 0.0,
        public float $discountPercent = 0.0,
        public ?int $assignedTo = null,
        public ?int $createdBy = null,
        public array $lineItems = [],
    ) {
        $this->validate();
    }

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            currency: $data['currency'] ?? 'USD',
            dealId: isset($data['deal_id']) ? (int) $data['deal_id'] : null,
            contactId: isset($data['contact_id']) ? (int) $data['contact_id'] : null,
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null,
            title: $data['title'] ?? null,
            validUntil: isset($data['valid_until'])
                ? new DateTimeImmutable($data['valid_until'])
                : null,
            terms: $data['terms'] ?? null,
            notes: $data['notes'] ?? null,
            internalNotes: $data['internal_notes'] ?? null,
            templateId: isset($data['template_id']) ? (int) $data['template_id'] : null,
            discountType: isset($data['discount_type'])
                ? DiscountType::from($data['discount_type'])
                : DiscountType::FIXED,
            discountAmount: (float) ($data['discount_amount'] ?? 0.0),
            discountPercent: (float) ($data['discount_percent'] ?? 0.0),
            assignedTo: isset($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            createdBy: isset($data['created_by']) ? (int) $data['created_by'] : null,
            lineItems: isset($data['line_items']) && is_array($data['line_items'])
                ? array_map(fn($item) => CreateQuoteLineItemDTO::fromArray($item), $data['line_items'])
                : [],
        );
    }

    /**
     * Validate the DTO.
     */
    private function validate(): void
    {
        if (strlen($this->currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a valid 3-letter ISO code');
        }

        if ($this->discountAmount < 0) {
            throw new InvalidArgumentException('Discount amount cannot be negative');
        }

        if ($this->discountPercent < 0 || $this->discountPercent > 100) {
            throw new InvalidArgumentException('Discount percent must be between 0 and 100');
        }

        foreach ($this->lineItems as $lineItem) {
            if (!$lineItem instanceof CreateQuoteLineItemDTO) {
                throw new InvalidArgumentException('Line items must be CreateQuoteLineItemDTO instances');
            }
        }
    }

    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'deal_id' => $this->dealId,
            'contact_id' => $this->contactId,
            'company_id' => $this->companyId,
            'title' => $this->title,
            'valid_until' => $this->validUntil?->format('Y-m-d'),
            'terms' => $this->terms,
            'notes' => $this->notes,
            'internal_notes' => $this->internalNotes,
            'template_id' => $this->templateId,
            'discount_type' => $this->discountType->value,
            'discount_amount' => $this->discountAmount,
            'discount_percent' => $this->discountPercent,
            'assigned_to' => $this->assignedTo,
            'created_by' => $this->createdBy,
            'line_items' => array_map(fn($item) => $item->toArray(), $this->lineItems),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
