<?php

declare(strict_types=1);

namespace App\Domain\Contract\ValueObjects;

/**
 * Value Object representing the billing frequency of a contract.
 */
enum BillingFrequency: string
{
    case OneTime = 'one_time';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnually = 'semi_annually';
    case Annually = 'annually';

    /**
     * Get the display label for this frequency.
     */
    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'One-Time',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::SemiAnnually => 'Semi-Annually',
            self::Annually => 'Annually',
        };
    }

    /**
     * Get the number of months in this billing cycle.
     */
    public function getMonths(): int
    {
        return match ($this) {
            self::OneTime => 0,
            self::Monthly => 1,
            self::Quarterly => 3,
            self::SemiAnnually => 6,
            self::Annually => 12,
        };
    }

    /**
     * Check if this is a recurring billing frequency.
     */
    public function isRecurring(): bool
    {
        return $this !== self::OneTime;
    }

    /**
     * Get all frequencies as an associative array.
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }
        return $result;
    }
}
