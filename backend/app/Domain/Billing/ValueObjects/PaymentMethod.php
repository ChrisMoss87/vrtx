<?php

declare(strict_types=1);

namespace App\Domain\Billing\ValueObjects;

/**
 * Enum representing payment methods.
 */
enum PaymentMethod: string
{
    case CASH = 'cash';
    case CHECK = 'check';
    case CREDIT_CARD = 'credit_card';
    case BANK_TRANSFER = 'bank_transfer';
    case PAYPAL = 'paypal';
    case STRIPE = 'stripe';
    case OTHER = 'other';

    /**
     * Get human-readable label for this payment method.
     */
    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::CHECK => 'Check',
            self::CREDIT_CARD => 'Credit Card',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::PAYPAL => 'PayPal',
            self::STRIPE => 'Stripe',
            self::OTHER => 'Other',
        };
    }

    /**
     * Check if this payment method requires external processing.
     */
    public function requiresExternalProcessing(): bool
    {
        return in_array($this, [self::CREDIT_CARD, self::PAYPAL, self::STRIPE]);
    }

    /**
     * Check if this payment method is manual.
     */
    public function isManual(): bool
    {
        return in_array($this, [self::CASH, self::CHECK, self::BANK_TRANSFER, self::OTHER]);
    }
}
