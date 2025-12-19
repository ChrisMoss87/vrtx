<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entities;

use App\Domain\Billing\ValueObjects\InvoiceStatus;
use App\Domain\Billing\ValueObjects\Money;
use App\Domain\Billing\ValueObjects\PaymentTerms;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Invoice aggregate root entity.
 *
 * Represents an invoice for goods or services provided.
 */
final class Invoice implements AggregateRoot
{
    use HasDomainEvents;

    /** @var InvoiceLineItem[] */
    private array $lineItems = [];

    /** @var InvoicePayment[] */
    private array $payments = [];

    private function __construct(
        private ?int $id,
        private string $invoiceNumber,
        private ?int $quoteId,
        private ?int $dealId,
        private ?int $contactId,
        private ?int $companyId,
        private InvoiceStatus $status,
        private ?string $title,
        private Money $subtotal,
        private Money $discountAmount,
        private Money $taxAmount,
        private Money $total,
        private Money $amountPaid,
        private Money $balanceDue,
        private string $currency,
        private DateTimeImmutable $issueDate,
        private DateTimeImmutable $dueDate,
        private PaymentTerms $paymentTerms,
        private ?string $notes,
        private ?string $internalNotes,
        private ?int $templateId,
        private string $viewToken,
        private ?DateTimeImmutable $sentAt,
        private ?string $sentToEmail,
        private ?DateTimeImmutable $viewedAt,
        private ?DateTimeImmutable $paidAt,
        private ?int $createdBy,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Create a new invoice.
     */
    public static function create(
        string $invoiceNumber,
        string $currency,
        DateTimeImmutable $issueDate,
        PaymentTerms $paymentTerms,
        ?int $quoteId = null,
        ?int $dealId = null,
        ?int $contactId = null,
        ?int $companyId = null,
        ?string $title = null,
        ?string $notes = null,
        ?string $internalNotes = null,
        ?int $templateId = null,
        ?int $createdBy = null,
    ): self {
        return new self(
            id: null,
            invoiceNumber: $invoiceNumber,
            quoteId: $quoteId,
            dealId: $dealId,
            contactId: $contactId,
            companyId: $companyId,
            status: InvoiceStatus::DRAFT,
            title: $title,
            subtotal: Money::zero($currency),
            discountAmount: Money::zero($currency),
            taxAmount: Money::zero($currency),
            total: Money::zero($currency),
            amountPaid: Money::zero($currency),
            balanceDue: Money::zero($currency),
            currency: $currency,
            issueDate: $issueDate,
            dueDate: $paymentTerms->calculateDueDate($issueDate),
            paymentTerms: $paymentTerms,
            notes: $notes,
            internalNotes: $internalNotes,
            templateId: $templateId,
            viewToken: bin2hex(random_bytes(16)),
            sentAt: null,
            sentToEmail: null,
            viewedAt: null,
            paidAt: null,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        string $invoiceNumber,
        ?int $quoteId,
        ?int $dealId,
        ?int $contactId,
        ?int $companyId,
        InvoiceStatus $status,
        ?string $title,
        Money $subtotal,
        Money $discountAmount,
        Money $taxAmount,
        Money $total,
        Money $amountPaid,
        Money $balanceDue,
        string $currency,
        DateTimeImmutable $issueDate,
        DateTimeImmutable $dueDate,
        PaymentTerms $paymentTerms,
        ?string $notes,
        ?string $internalNotes,
        ?int $templateId,
        string $viewToken,
        ?DateTimeImmutable $sentAt,
        ?string $sentToEmail,
        ?DateTimeImmutable $viewedAt,
        ?DateTimeImmutable $paidAt,
        ?int $createdBy,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            invoiceNumber: $invoiceNumber,
            quoteId: $quoteId,
            dealId: $dealId,
            contactId: $contactId,
            companyId: $companyId,
            status: $status,
            title: $title,
            subtotal: $subtotal,
            discountAmount: $discountAmount,
            taxAmount: $taxAmount,
            total: $total,
            amountPaid: $amountPaid,
            balanceDue: $balanceDue,
            currency: $currency,
            issueDate: $issueDate,
            dueDate: $dueDate,
            paymentTerms: $paymentTerms,
            notes: $notes,
            internalNotes: $internalNotes,
            templateId: $templateId,
            viewToken: $viewToken,
            sentAt: $sentAt,
            sentToEmail: $sentToEmail,
            viewedAt: $viewedAt,
            paidAt: $paidAt,
            createdBy: $createdBy,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Add a line item to the invoice.
     */
    public function addLineItem(InvoiceLineItem $lineItem): void
    {
        if (!$this->status->isEditable()) {
            throw new InvalidArgumentException('Cannot modify an invoice that is not in draft status');
        }

        $this->lineItems[] = $lineItem;
        $this->recalculateTotals();
    }

    /**
     * Set all line items at once.
     *
     * @param InvoiceLineItem[] $lineItems
     */
    public function setLineItems(array $lineItems): void
    {
        if (!$this->status->isEditable()) {
            throw new InvalidArgumentException('Cannot modify an invoice that is not in draft status');
        }

        $this->lineItems = $lineItems;
        $this->recalculateTotals();
    }

    /**
     * Recalculate invoice totals from line items.
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
        $this->total = $subtotal->subtract($this->discountAmount)->add($taxAmount);
        $this->balanceDue = $this->total->subtract($this->amountPaid);
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Mark the invoice as sent.
     */
    public function markAsSent(string $toEmail): void
    {
        if (!$this->status->canBeSent()) {
            throw new InvalidArgumentException('Invoice cannot be sent in its current status');
        }

        $this->status = InvoiceStatus::SENT;
        $this->sentAt = new DateTimeImmutable();
        $this->sentToEmail = $toEmail;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Mark the invoice as viewed.
     */
    public function markAsViewed(): void
    {
        if ($this->viewedAt === null && $this->status === InvoiceStatus::SENT) {
            $this->viewedAt = new DateTimeImmutable();
            $this->status = InvoiceStatus::VIEWED;
            $this->updatedAt = new DateTimeImmutable();
        }
    }

    /**
     * Record a payment.
     */
    public function recordPayment(InvoicePayment $payment): void
    {
        if (!$this->status->canRecordPayment()) {
            throw new InvalidArgumentException('Cannot record payment for this invoice');
        }

        $this->payments[] = $payment;
        $this->recalculatePayments();
    }

    /**
     * Set all payments at once.
     *
     * @param InvoicePayment[] $payments
     */
    public function setPayments(array $payments): void
    {
        $this->payments = $payments;
        $this->recalculatePayments();
    }

    /**
     * Recalculate payment totals and update status.
     */
    public function recalculatePayments(): void
    {
        $amountPaid = Money::zero($this->currency);

        foreach ($this->payments as $payment) {
            $amountPaid = $amountPaid->add($payment->getAmount());
        }

        $this->amountPaid = $amountPaid;
        $this->balanceDue = $this->total->subtract($amountPaid);

        // Update status based on payment
        if ($this->balanceDue->isZero() || $this->balanceDue->lessThan(Money::zero($this->currency))) {
            $this->status = InvoiceStatus::PAID;
            $this->paidAt = new DateTimeImmutable();
        } elseif ($amountPaid->isPositive()) {
            $this->status = InvoiceStatus::PARTIAL;
        }

        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Mark the invoice as overdue.
     */
    public function markAsOverdue(): void
    {
        if ($this->status->isTerminal()) {
            return; // Already in a terminal state
        }

        if ($this->isOverdue()) {
            $this->status = InvoiceStatus::OVERDUE;
            $this->updatedAt = new DateTimeImmutable();
        }
    }

    /**
     * Cancel the invoice.
     */
    public function cancel(): void
    {
        if ($this->status === InvoiceStatus::PAID) {
            throw new InvalidArgumentException('Cannot cancel a paid invoice');
        }

        $this->status = InvoiceStatus::CANCELLED;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Update invoice details.
     */
    public function update(
        ?string $title = null,
        ?DateTimeImmutable $issueDate = null,
        ?DateTimeImmutable $dueDate = null,
        ?PaymentTerms $paymentTerms = null,
        ?Money $discountAmount = null,
        ?string $notes = null,
        ?string $internalNotes = null,
        ?int $templateId = null,
    ): void {
        if (!$this->status->isEditable()) {
            throw new InvalidArgumentException('Cannot modify an invoice that is not in draft status');
        }

        if ($title !== null) {
            $this->title = $title;
        }

        if ($issueDate !== null) {
            $this->issueDate = $issueDate;
        }

        if ($dueDate !== null) {
            $this->dueDate = $dueDate;
        }

        if ($paymentTerms !== null) {
            $this->paymentTerms = $paymentTerms;
        }

        if ($discountAmount !== null) {
            $this->discountAmount = $discountAmount;
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

        $this->updatedAt = new DateTimeImmutable();
        $this->recalculateTotals();
    }

    /**
     * Check if the invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->dueDate < new DateTimeImmutable() && $this->status->isUnpaid();
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

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function getQuoteId(): ?int
    {
        return $this->quoteId;
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

    public function getStatus(): InvoiceStatus
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

    public function getTaxAmount(): Money
    {
        return $this->taxAmount;
    }

    public function getTotal(): Money
    {
        return $this->total;
    }

    public function getAmountPaid(): Money
    {
        return $this->amountPaid;
    }

    public function getBalanceDue(): Money
    {
        return $this->balanceDue;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getIssueDate(): DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function getDueDate(): DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function getPaymentTerms(): PaymentTerms
    {
        return $this->paymentTerms;
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

    public function getViewToken(): string
    {
        return $this->viewToken;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getSentToEmail(): ?string
    {
        return $this->sentToEmail;
    }

    public function getViewedAt(): ?DateTimeImmutable
    {
        return $this->viewedAt;
    }

    public function getPaidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
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
     * @return InvoiceLineItem[]
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    /**
     * @return InvoicePayment[]
     */
    public function getPayments(): array
    {
        return $this->payments;
    }

    /**
     * Get the public URL for viewing this invoice.
     */
    public function getPublicUrl(): string
    {
        return url("/invoice/{$this->viewToken}");
    }
}
