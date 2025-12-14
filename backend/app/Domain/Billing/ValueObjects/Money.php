<?php

declare(strict_types=1);

namespace App\Domain\Billing\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Value object representing a monetary amount with currency.
 */
final readonly class Money implements JsonSerializable
{
    public function __construct(
        private float $amount,
        private string $currency = 'USD',
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative');
        }

        if (empty($currency) || strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a valid 3-letter ISO code');
        }
    }

    /**
     * Create from cents/smallest unit.
     */
    public static function fromCents(int $cents, string $currency = 'USD'): self
    {
        return new self($cents / 100, $currency);
    }

    /**
     * Create zero money.
     */
    public static function zero(string $currency = 'USD'): self
    {
        return new self(0.0, $currency);
    }

    /**
     * Get the amount.
     */
    public function amount(): float
    {
        return $this->amount;
    }

    /**
     * Get the currency code.
     */
    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * Get amount in cents/smallest unit.
     */
    public function toCents(): int
    {
        return (int) round($this->amount * 100);
    }

    /**
     * Add another money value.
     */
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Subtract another money value.
     */
    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        $result = $this->amount - $other->amount;

        if ($result < 0) {
            throw new InvalidArgumentException('Subtraction would result in negative amount');
        }

        return new self($result, $this->currency);
    }

    /**
     * Multiply by a factor.
     */
    public function multiply(float $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException('Multiplication factor cannot be negative');
        }

        return new self($this->amount * $factor, $this->currency);
    }

    /**
     * Check if this money is zero.
     */
    public function isZero(): bool
    {
        return abs($this->amount) < 0.01;
    }

    /**
     * Check if this money is positive.
     */
    public function isPositive(): bool
    {
        return $this->amount > 0.01;
    }

    /**
     * Check if greater than another money value.
     */
    public function greaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount > $other->amount;
    }

    /**
     * Check if less than another money value.
     */
    public function lessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount < $other->amount;
    }

    /**
     * Check if equal to another money value.
     */
    public function equals(Money $other): bool
    {
        return $this->currency === $other->currency
            && abs($this->amount - $other->amount) < 0.01;
    }

    /**
     * Format as string with currency symbol.
     */
    public function format(): string
    {
        $symbol = $this->getCurrencySymbol();
        return $symbol . number_format($this->amount, 2);
    }

    /**
     * Get currency symbol.
     */
    private function getCurrencySymbol(): string
    {
        return match ($this->currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'CHF' => 'CHF ',
            'CNY' => '¥',
            'INR' => '₹',
            'BRL' => 'R$',
            default => $this->currency . ' ',
        };
    }

    /**
     * Assert that another money value has the same currency.
     */
    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on different currencies: {$this->currency} and {$other->currency}"
            );
        }
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
