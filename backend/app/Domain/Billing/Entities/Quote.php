<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entities;

use App\Domain\Billing\ValueObjects\DiscountType;
use App\Domain\Billing\ValueObjects\Money;
use App\Domain\Billing\ValueObjects\QuoteStatus;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Quote aggregate root entity.
 *
 * Represents a price quotation that can be sent to customers.
 */
final class Quote implements AggregateRoot
{
    use HasDomainEvents;

    /** @var QuoteLineItem[] */
    private array $lineItems = [];

    private function __construct(
        private ?int $id,
        private string $quoteNumber,
        private ?int $dealId,
        private ?int $contactId,
        private ?int $companyId,
        private QuoteStatus $status,
        private ?string $title,
        private Money $subtotal,
        private Money $discountAmount,
        private DiscountType $discountType,
        private float $discountPercent,
        private Money $taxAmount,
        private Money $total,
        private string $currency,
        private ?DateTimeImmutable $validUntil,
        private ?string $terms,
        private ?string $notes,
        private ?string $internalNotes,
        private ?int $templateId,
        private int $version,
        private string $viewToken,
        private ?DateTimeImmutable $acceptedAt,
        private ?string $acceptedBy,
        private ?string $acceptedSignature,
        private ?string $acceptedIp,
        private ?DateTimeImmutable $rejectedAt,
        private ?string $rejectedBy,
        private ?string $rejectionReason,
        private ?DateTimeImmutable $viewedAt,
        private ?DateTimeImmutable $sentAt,
        private ?string $sentToEmail,
        private ?int $createdBy,
        private ?int $assignedTo,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Create a new quote.
     */
    public static function create(
        string $quoteNumber,
        string $currency,
        ?int $dealId = null,
        ?int $contactId = null,
        ?int $companyId = null,
        ?string $title = null,
        ?DateTimeImmutable $validUntil = null,
        ?string $terms = null,
        ?string $notes = null,
        ?string $internalNotes = null,
        ?int $templateId = null,
        DiscountType $discountType = DiscountType::FIXED,
        float $discountAmount = 0.0,
        float $discountPercent = 0.0,
        ?int $assignedTo = null,
        ?int $createdBy = null,
    ): self {
        return new self(
            id: null,
            quoteNumber: $quoteNumber,
            dealId: $dealId,
            contactId: $contactId,
            companyId: $companyId,
            status: QuoteStatus::DRAFT,
            title: $title,
            subtotal: Money::zero($currency),
            discountAmount: new Money($discountAmount, $currency),
            discountType: $discountType,
            discountPercent: $discountPercent,
            taxAmount: Money::zero($currency),
            total: Money::zero($currency),
            currency: $currency,
            validUntil: $validUntil,
            terms: $terms,
            notes: $notes,
            internalNotes: $internalNotes,
            templateId: $templateId,
            version: 1,
            viewToken: bin2hex(random_bytes(16)),
            acceptedAt: null,
            acceptedBy: null,
            acceptedSignature: null,
            acceptedIp: null,
            rejectedAt: null,
            rejectedBy: null,
            rejectionReason: null,
            viewedAt: null,
            sentAt: null,
            sentToEmail: null,
            createdBy: $createdBy,
            assignedTo: $assignedTo,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        string $quoteNumber,
        ?int $dealId,
        ?int $contactId,
        ?int $companyId,
        QuoteStatus $status,
        ?string $title,
        Money $subtotal,
        Money $discountAmount,
        DiscountType $discountType,
        float $discountPercent,
        Money $taxAmount,
        Money $total,
        string $currency,
        ?DateTimeImmutable $validUntil,
        ?string $terms,
        ?string $notes,
        ?string $internalNotes,
        ?int $templateId,
        int $version,
        string $viewToken,
        ?DateTimeImmutable $acceptedAt,
        ?string $acceptedBy,
        ?string $acceptedSignature,
        ?string $acceptedIp,
        ?DateTimeImmutable $rejectedAt,
        ?string $rejectedBy,
        ?string $rejectionReason,
        ?DateTimeImmutable $viewedAt,
        ?DateTimeImmutable $sentAt,
        ?string $sentToEmail,
        ?int $createdBy,
        ?int $assignedTo,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            quoteNumber: $quoteNumber,
            dealId: $dealId,
            contactId: $contactId,
            companyId: $companyId,
            status: $status,
            title: $title,
            subtotal: $subtotal,
            discountAmount: $discountAmount,
            discountType: $discountType,
            discountPercent: $discountPercent,
            taxAmount: $taxAmount,
            total: $total,
            currency: $currency,
            validUntil: $validUntil,
            terms: $terms,
            notes: $notes,
            internalNotes: $internalNotes,
            templateId: $templateId,
            version: $version,
            viewToken: $viewToken,
            acceptedAt: $acceptedAt,
            acceptedBy: $acceptedBy,
            acceptedSignature: $acceptedSignature,
            acceptedIp: $acceptedIp,
            rejectedAt: $rejectedAt,
            rejectedBy: $rejectedBy,
            rejectionReason: $rejectionReason,
            viewedAt: $viewedAt,
            sentAt: $sentAt,
            sentToEmail: $sentToEmail,
            createdBy: $createdBy,
            assignedTo: $assignedTo,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Add a line item to the quote.
     */
    public function addLineItem(QuoteLineItem $lineItem): void
    {
        if (!$this->status->isEditable()) {
            throw new InvalidArgumentException('Cannot modify a quote that is not in draft status');
        }

        $this->lineItems[] = $lineItem;
        $this->recalculateTotals();
    }

    /**
     * Set all line items at once.
     *
     * @param QuoteLineItem[] $lineItems
     */
    public function setLineItems(array $lineItems): void
    {
        if (!$this->status->isEditable()) {
            throw new InvalidArgumentException('Cannot modify a quote that is not in draft status');
        }

        $this->lineItems = $lineItems;
        $this->recalculateTotals();
    }

    /**
     * Recalculate quote totals from line items.
     */
    public function recalculateTotals(): void
    {
        $subtotal = Money::zero($this->currency);
        $taxAmount = Money::zero($this->currency);

        foreach ($this->lineItems as $lineItem) {
            $subtotal = $subtotal->add($lineItem->calculateSubtotal());
            $taxAmount = $taxAmount->add($lineItem->calculateTax());
        }

        $this->subtotal = $subtotal;
        $this->taxAmount = $taxAmount;

        // Calculate discount
        if ($this->discountType === DiscountType::PERCENT && $this->discountPercent > 0) {
            $this->discountAmount = $subtotal->multiply($this->discountPercent / 100);
        }

        // Calculate total
        $this->total = $subtotal->subtract($this->discountAmount)->add($taxAmount);
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Mark the quote as sent.
     */
    public function markAsSent(string $toEmail): void
    {
        if (!$this->status->canBeSent()) {
            throw new InvalidArgumentException('Quote cannot be sent in its current status');
        }

        $this->status = QuoteStatus::SENT;
        $this->sentAt = new DateTimeImmutable();
        $this->sentToEmail = $toEmail;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Mark the quote as viewed.
     */
    public function markAsViewed(): void
    {
        if ($this->viewedAt === null && $this->status === QuoteStatus::SENT) {
            $this->viewedAt = new DateTimeImmutable();
            $this->status = QuoteStatus::VIEWED;
            $this->updatedAt = new DateTimeImmutable();
        }
    }

    /**
     * Accept the quote.
     */
    public function accept(string $acceptedBy, ?string $signature = null, ?string $ip = null): void
    {
        if (!$this->status->canBeAccepted()) {
            throw new InvalidArgumentException('Quote cannot be accepted in its current status');
        }

        if ($this->isExpired()) {
            throw new InvalidArgumentException('Quote has expired');
        }

        $this->status = QuoteStatus::ACCEPTED;
        $this->acceptedAt = new DateTimeImmutable();
        $this->acceptedBy = $acceptedBy;
        $this->acceptedSignature = $signature;
        $this->acceptedIp = $ip;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Reject the quote.
     */
    public function reject(string $rejectedBy, ?string $reason = null): void
    {
        if (!$this->status->canBeAccepted()) {
            throw new InvalidArgumentException('Quote cannot be rejected in its current status');
        }

        $this->status = QuoteStatus::REJECTED;
        $this->rejectedAt = new DateTimeImmutable();
        $this->rejectedBy = $rejectedBy;
        $this->rejectionReason = $reason;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Mark the quote as expired.
     */
    public function markAsExpired(): void
    {
        if ($this->status->isTerminal()) {
            return; // Already in a terminal state
        }

        $this->status = QuoteStatus::EXPIRED;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Update quote details.
     */
    public function update(
        ?string $title = null,
        ?DateTimeImmutable $validUntil = null,
        ?string $terms = null,
        ?string $notes = null,
        ?string $internalNotes = null,
        ?int $templateId = null,
        ?DiscountType $discountType = null,
        ?float $discountAmount = null,
        ?float $discountPercent = null,
        ?int $assignedTo = null,
    ): void {
        if (!$this->status->isEditable()) {
            throw new InvalidArgumentException('Cannot modify a quote that is not in draft status');
        }

        if ($title !== null) {
            $this->title = $title;
        }

        if ($validUntil !== null) {
            $this->validUntil = $validUntil;
        }

        if ($terms !== null) {
            $this->terms = $terms;
        }

        if ($notes !== null) {
            $this->notes = $notes;
        }

        if ($internalNotes !== null) {
            $this->internalNotes = $internalNotes;
        }

        if ($templateId !== null) {
            $this->templateId = $templateId;
        }

        if ($discountType !== null) {
            $this->discountType = $discountType;
        }

        if ($discountAmount !== null) {
            $this->discountAmount = new Money($discountAmount, $this->currency);
        }

        if ($discountPercent !== null) {
            $this->discountPercent = $discountPercent;
        }

        if ($assignedTo !== null) {
            $this->assignedTo = $assignedTo;
        }

        $this->updatedAt = new DateTimeImmutable();
        $this->recalculateTotals();
    }

    /**
     * Check if the quote has expired.
     */
    public function isExpired(): bool
    {
        return $this->validUntil !== null && $this->validUntil < new DateTimeImmutable();
    }

    /**
     * Increment version (for tracking changes).
     */
    public function incrementVersion(): void
    {
        $this->version++;
        $this->updatedAt = new DateTimeImmutable();
    }

    // ========== AggregateRoot Implementation ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function equals(\App\Domain\Shared\Contracts\Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->id !== null && $this->id === $other->id;
    }

    // ========== Getters ==========

    public function getQuoteNumber(): string
    {
        return $this->quoteNumber;
    }

    public function getDealId(): ?int
    {
        return $this->dealId;
    }

    public function getContactId(): ?int
    {
        return $this->contactId;
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }

    public function getStatus(): QuoteStatus
    {
        return $this->status;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getSubtotal(): Money
    {
        return $this->subtotal;
    }

    public function getDiscountAmount(): Money
    {
        return $this->discountAmount;
    }

    public function getDiscountType(): DiscountType
    {
        return $this->discountType;
    }

    public function getDiscountPercent(): float
    {
        return $this->discountPercent;
    }

    public function getTaxAmount(): Money
    {
        return $this->taxAmount;
    }

    public function getTotal(): Money
    {
        return $this->total;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getValidUntil(): ?DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function getTerms(): ?string
    {
        return $this->terms;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getInternalNotes(): ?string
    {
        return $this->internalNotes;
    }

    public function getTemplateId(): ?int
    {
        return $this->templateId;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getViewToken(): string
    {
        return $this->viewToken;
    }

    public function getAcceptedAt(): ?DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function getAcceptedBy(): ?string
    {
        return $this->acceptedBy;
    }

    public function getAcceptedSignature(): ?string
    {
        return $this->acceptedSignature;
    }

    public function getAcceptedIp(): ?string
    {
        return $this->acceptedIp;
    }

    public function getRejectedAt(): ?DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function getRejectedBy(): ?string
    {
        return $this->rejectedBy;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function getViewedAt(): ?DateTimeImmutable
    {
        return $this->viewedAt;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getSentToEmail(): ?string
    {
        return $this->sentToEmail;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function getAssignedTo(): ?int
    {
        return $this->assignedTo;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return QuoteLineItem[]
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    /**
     * Get the public URL for viewing this quote.
     */
    public function getPublicUrl(): string
    {
        return url("/quote/{$this->viewToken}");
    }
}
