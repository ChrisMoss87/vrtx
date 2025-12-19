<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a payment is received for an invoice.
 */
final class PaymentReceived extends DomainEvent
{
    public function __construct(
        private readonly int $invoiceId,
        private readonly string $invoiceNumber,
        private readonly int $paymentId,
        private readonly float $paymentAmount,
        private readonly float $balanceDue,
        private readonly string $currency,
        private readonly ?string $paymentMethod,
        private readonly string $paymentDate,
        private readonly bool $isFullyPaid,
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

    public function paymentId(): int
    {
        return $this->paymentId;
    }

    public function paymentAmount(): float
    {
        return $this->paymentAmount;
    }

    public function balanceDue(): float
    {
        return $this->balanceDue;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function paymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function paymentDate(): string
    {
        return $this->paymentDate;
    }

    public function isFullyPaid(): bool
    {
        return $this->isFullyPaid;
    }

    public function toPayload(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'payment_id' => $this->paymentId,
            'payment_amount' => $this->paymentAmount,
            'balance_due' => $this->balanceDue,
            'currency' => $this->currency,
            'payment_method' => $this->paymentMethod,
            'payment_date' => $this->paymentDate,
            'is_fully_paid' => $this->isFullyPaid,
        ];
    }
}
