<?php

declare(strict_types=1);

namespace App\Domain\Goal\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable Money value object.
 */
final class Money
{
    private function __construct(
        private readonly float $amount,
        private readonly string $currency,
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }

        if (empty($currency) || strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO code');
        }
    }

    public static function from(float $amount, string $currency): self
    {
        return new self($amount, strtoupper($currency));
    }

    public static function zero(string $currency): self
    {
        return new self(0.0, strtoupper($currency));
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        $newAmount = $this->amount - $other->amount;

        if ($newAmount < 0) {
            throw new InvalidArgumentException('Subtraction would result in negative amount');
        }

        return new self($newAmount, $this->currency);
    }

    public function multiply(float $multiplier): self
    {
        if ($multiplier < 0) {
            throw new InvalidArgumentException('Multiplier cannot be negative');
        }

        return new self($this->amount * $multiplier, $this->currency);
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount > $other->amount;
    }

    public function isGreaterThanOrEqual(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount >= $other->amount;
    }

    public function isLessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount < $other->amount;
    }

    public function isLessThanOrEqual(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount <= $other->amount;
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function isZero(): bool
    {
        return $this->amount === 0.0;
    }

    public function format(int $decimals = 2): string
    {
        return sprintf('%s %s', $this->currency, number_format($this->amount, $decimals));
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                sprintf('Cannot operate on different currencies: %s and %s', $this->currency, $other->currency)
            );
        }
    }
}
