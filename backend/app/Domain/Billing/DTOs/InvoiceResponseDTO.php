<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

use App\Domain\Billing\Entities\Invoice;
use JsonSerializable;

/**
 * Data Transfer Object for invoice responses.
 */
final readonly class InvoiceResponseDTO implements JsonSerializable
{
    /**
     * @param array<InvoiceLineItemResponseDTO> $lineItems
     * @param array<InvoicePaymentResponseDTO> $payments
     */
    public function __construct(
        public int $id,
        public string $invoiceNumber,
        public ?int $quoteId,
        public ?int $dealId,
        public ?int $contactId,
        public ?int $companyId,
        public string $status,
        public ?string $title,
        public float $subtotal,
        public float $discountAmount,
        public float $taxAmount,
        public float $total,
        public float $amountPaid,
        public float $balanceDue,
        public string $currency,
        public string $issueDate,
        public string $dueDate,
        public string $paymentTerms,
        public ?string $notes,
        public ?string $internalNotes,
        public ?int $templateId,
        public string $viewToken,
        public ?string $sentAt,
        public ?string $sentToEmail,
        public ?string $viewedAt,
        public ?string $paidAt,
        public ?int $createdBy,
        public string $createdAt,
        public ?string $updatedAt,
        public array $lineItems,
        public array $payments,
        public string $publicUrl,
        public bool $isOverdue,
    ) {}

    public static function fromEntity(Invoice $invoice): self
    {
        return new self(
            id: $invoice->getId(),
            invoiceNumber: $invoice->getInvoiceNumber(),
            quoteId: $invoice->getQuoteId(),
            dealId: $invoice->getDealId(),
            contactId: $invoice->getContactId(),
            companyId: $invoice->getCompanyId(),
            status: $invoice->getStatus()->value,
            title: $invoice->getTitle(),
            subtotal: $invoice->getSubtotal()->amount(),
            discountAmount: $invoice->getDiscountAmount()->amount(),
            taxAmount: $invoice->getTaxAmount()->amount(),
            total: $invoice->getTotal()->amount(),
            amountPaid: $invoice->getAmountPaid()->amount(),
            balanceDue: $invoice->getBalanceDue()->amount(),
            currency: $invoice->getCurrency(),
            issueDate: $invoice->getIssueDate()->format('Y-m-d'),
            dueDate: $invoice->getDueDate()->format('Y-m-d'),
            paymentTerms: $invoice->getPaymentTerms()->value,
            notes: $invoice->getNotes(),
            internalNotes: $invoice->getInternalNotes(),
            templateId: $invoice->getTemplateId(),
            viewToken: $invoice->getViewToken(),
            sentAt: $invoice->getSentAt()?->format('Y-m-d H:i:s'),
            sentToEmail: $invoice->getSentToEmail(),
            viewedAt: $invoice->getViewedAt()?->format('Y-m-d H:i:s'),
            paidAt: $invoice->getPaidAt()?->format('Y-m-d H:i:s'),
            createdBy: $invoice->getCreatedBy(),
            createdAt: $invoice->getCreatedAt()->format('Y-m-d H:i:s'),
            updatedAt: $invoice->getUpdatedAt()?->format('Y-m-d H:i:s'),
            lineItems: array_map(
                fn($item) => InvoiceLineItemResponseDTO::fromEntity($item),
                $invoice->getLineItems()
            ),
            payments: array_map(
                fn($payment) => InvoicePaymentResponseDTO::fromEntity($payment),
                $invoice->getPayments()
            ),
            publicUrl: $invoice->getPublicUrl(),
            isOverdue: $invoice->isOverdue(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoiceNumber,
            'quote_id' => $this->quoteId,
            'deal_id' => $this->dealId,
            'contact_id' => $this->contactId,
            'company_id' => $this->companyId,
            'status' => $this->status,
            'title' => $this->title,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discountAmount,
            'tax_amount' => $this->taxAmount,
            'total' => $this->total,
            'amount_paid' => $this->amountPaid,
            'balance_due' => $this->balanceDue,
            'currency' => $this->currency,
            'issue_date' => $this->issueDate,
            'due_date' => $this->dueDate,
            'payment_terms' => $this->paymentTerms,
            'notes' => $this->notes,
            'internal_notes' => $this->internalNotes,
            'template_id' => $this->templateId,
            'view_token' => $this->viewToken,
            'sent_at' => $this->sentAt,
            'sent_to_email' => $this->sentToEmail,
            'viewed_at' => $this->viewedAt,
            'paid_at' => $this->paidAt,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'line_items' => array_map(fn($item) => $item->toArray(), $this->lineItems),
            'payments' => array_map(fn($payment) => $payment->toArray(), $this->payments),
            'public_url' => $this->publicUrl,
            'is_overdue' => $this->isOverdue,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
