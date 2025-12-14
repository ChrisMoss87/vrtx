<?php

declare(strict_types=1);

namespace App\Domain\Billing\ValueObjects;

/**
 * Enum representing payment terms.
 */
enum PaymentTerms: string
{
    case DUE_ON_RECEIPT = 'due_on_receipt';
    case NET_7 = 'net_7';
    case NET_15 = 'net_15';
    case NET_30 = 'net_30';
    case NET_45 = 'net_45';
    case NET_60 = 'net_60';
    case NET_90 = 'net_90';

    /**
     * Get human-readable label for these payment terms.
     */
    public function label(): string
    {
        return match ($this) {
            self::DUE_ON_RECEIPT => 'Due on Receipt',
            self::NET_7 => 'Net 7',
            self::NET_15 => 'Net 15',
            self::NET_30 => 'Net 30',
            self::NET_45 => 'Net 45',
            self::NET_60 => 'Net 60',
            self::NET_90 => 'Net 90',
        };
    }

    /**
     * Get the number of days until payment is due.
     */
    public function daysUntilDue(): int
    {
        return match ($this) {
            self::DUE_ON_RECEIPT => 0,
            self::NET_7 => 7,
            self::NET_15 => 15,
            self::NET_30 => 30,
            self::NET_45 => 45,
            self::NET_60 => 60,
            self::NET_90 => 90,
        };
    }

    /**
     * Calculate due date from issue date.
     */
    public function calculateDueDate(\DateTimeImmutable $issueDate): \DateTimeImmutable
    {
        return $issueDate->modify("+{$this->daysUntilDue()} days");
    }
}
