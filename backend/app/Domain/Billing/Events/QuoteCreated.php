<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a quote is created.
 */
final class QuoteCreated extends DomainEvent
{
    public function __construct(
        private readonly int $quoteId,
        private readonly string $quoteNumber,
        private readonly ?int $dealId,
        private readonly ?int $contactId,
        private readonly ?int $companyId,
        private readonly float $total,
        private readonly string $currency,
        private readonly ?int $createdBy,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int
    {
        return $this->quoteId;
    }

    public function aggregateType(): string
    {
        return 'Quote';
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function quoteNumber(): string
    {
        return $this->quoteNumber;
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

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }

    public function toPayload(): array
    {
        return [
            'quote_id' => $this->quoteId,
            'quote_number' => $this->quoteNumber,
            'deal_id' => $this->dealId,
            'contact_id' => $this->contactId,
            'company_id' => $this->companyId,
            'total' => $this->total,
            'currency' => $this->currency,
            'created_by' => $this->createdBy,
        ];
    }
}
