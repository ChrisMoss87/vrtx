<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when an invoice is created.
 */
final class InvoiceCreated extends DomainEvent
{
    public function __construct(
        private readonly int $invoiceId,
        private readonly string $invoiceNumber,
        private readonly ?int $quoteId,
        private readonly ?int $dealId,
        private readonly ?int $contactId,
        private readonly ?int $companyId,
        private readonly float $total,
        private readonly string $currency,
        private readonly string $dueDate,
        private readonly ?int $createdBy,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int
    {
        return $this->invoiceId;
    }

    public function aggregateType(): string
    {
        return 'Invoice';
    }

    public function invoiceId(): int
    {
        return $this->invoiceId;
    }

    public function invoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function quoteId(): ?int
    {
        return $this->quoteId;
    }

    public function dealId(): ?int
    {
        return $this->dealId;
    }

    public function contactId(): ?int
    {
        return $this->contactId;
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    public function total(): float
    {
        return $this->total;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function dueDate(): string
    {
        return $this->dueDate;
    }

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }

    public function toPayload(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'quote_id' => $this->quoteId,
            'deal_id' => $this->dealId,
            'contact_id' => $this->contactId,
            'company_id' => $this->companyId,
            'total' => $this->total,
            'currency' => $this->currency,
            'due_date' => $this->dueDate,
            'created_by' => $this->createdBy,
        ];
    }
}
