<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a quote is accepted by the customer.
 */
final class QuoteAccepted extends DomainEvent
{
    public function __construct(
        private readonly int $quoteId,
        private readonly string $quoteNumber,
        private readonly string $acceptedBy,
        private readonly ?int $dealId,
        private readonly float $total,
        private readonly string $currency,
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

    public function acceptedBy(): string
    {
        return $this->acceptedBy;
    }

    public function dealId(): ?int
    {
        return $this->dealId;
    }

    public function total(): float
    {
        return $this->total;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function toPayload(): array
    {
        return [
            'quote_id' => $this->quoteId,
            'quote_number' => $this->quoteNumber,
            'accepted_by' => $this->acceptedBy,
            'deal_id' => $this->dealId,
            'total' => $this->total,
            'currency' => $this->currency,
        ];
    }
}
