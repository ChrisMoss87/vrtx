<?php

declare(strict_types=1);

namespace App\Domain\Billing\ValueObjects;

/**
 * Enum representing discount types.
 */
enum DiscountType: string
{
    case FIXED = 'fixed';
    case PERCENT = 'percent';

    /**
     * Get human-readable label for this discount type.
     */
    public function label(): string
    {
        return match ($this) {
            self::FIXED => 'Fixed Amount',
            self::PERCENT => 'Percentage',
        };
    }

    /**
     * Calculate discount amount.
     */
    public function calculateDiscount(float $baseAmount, float $discountValue): float
    {
        return match ($this) {
            self::FIXED => $discountValue,
            self::PERCENT => $baseAmount * ($discountValue / 100),
        };
    }
}
