<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

use App\Domain\Billing\Entities\InvoicePayment;
use JsonSerializable;

/**
 * Data Transfer Object for invoice payment responses.
 */
final readonly class InvoicePaymentResponseDTO implements JsonSerializable
{
    public function __construct(
        public ?int $id,
        public float $amount,
        public string $paymentDate,
        public ?string $paymentMethod,
        public ?string $reference,
        public ?string $notes,
        public ?int $createdBy,
        public string $createdAt,
    ) {}

    public static function fromEntity(InvoicePayment $payment): self
    {
        return new self(
            id: $payment->getId(),
            amount: $payment->getAmount()->amount(),
            paymentDate: $payment->getPaymentDate()->format('Y-m-d'),
            paymentMethod: $payment->getPaymentMethod()?->value,
            reference: $payment->getReference(),
            notes: $payment->getNotes(),
            createdBy: $payment->getCreatedBy(),
            createdAt: $payment->getCreatedAt()->format('Y-m-d H:i:s'),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'payment_date' => $this->paymentDate,
            'payment_method' => $this->paymentMethod,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
