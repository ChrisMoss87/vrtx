<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entities;

use App\Domain\Billing\ValueObjects\Money;
use App\Domain\Billing\ValueObjects\PaymentMethod;
use DateTimeImmutable;

/**
 * Invoice payment entity.
 */
final class InvoicePayment
{
    private function __construct(
        private ?int $id,
        private int $invoiceId,
        private Money $amount,
        private DateTimeImmutable $paymentDate,
        private ?PaymentMethod $paymentMethod,
        private ?string $reference,
        private ?string $notes,
        private ?int $createdBy,
        private ?DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        int $invoiceId,
        Money $amount,
        DateTimeImmutable $paymentDate,
        ?PaymentMethod $paymentMethod = null,
        ?string $reference = null,
        ?string $notes = null,
        ?int $createdBy = null,
    ): self {
        return new self(
            id: null,
            invoiceId: $invoiceId,
            amount: $amount,
            paymentDate: $paymentDate,
            paymentMethod: $paymentMethod,
            reference: $reference,
            notes: $notes,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable(),
        );
    }

    public static function reconstitute(
        int $id,
        int $invoiceId,
        Money $amount,
        DateTimeImmutable $paymentDate,
        ?PaymentMethod $paymentMethod,
        ?string $reference,
        ?string $notes,
        ?int $createdBy,
        ?DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            invoiceId: $invoiceId,
            amount: $amount,
            paymentDate: $paymentDate,
            paymentMethod: $paymentMethod,
            reference: $reference,
            notes: $notes,
            createdBy: $createdBy,
            createdAt: $createdAt,
        );
    }

    // ========== Getters ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoiceId(): int
    {
        return $this->invoiceId;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getPaymentDate(): DateTimeImmutable
    {
        return $this->paymentDate;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }
}
