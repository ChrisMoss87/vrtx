<?php

declare(strict_types=1);

namespace App\Domain\Plugin\ValueObjects;

use InvalidArgumentException;

final readonly class Money
{
    private function __construct(
        private int $cents,
        private string $currency = 'USD'
    ) {
        if ($cents < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative');
        }
    }

    public static function fromCents(int $cents, string $currency = 'USD'): self
    {
        return new self($cents, strtoupper($currency));
    }

    public static function fromDecimal(float $amount, string $currency = 'USD'): self
    {
        return new self((int) round($amount * 100), strtoupper($currency));
    }

    public static function zero(string $currency = 'USD'): self
    {
        return new self(0, strtoupper($currency));
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function amount(): float
    {
        return $this->cents / 100;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->cents + $other->cents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        $result = $this->cents - $other->cents;

        if ($result < 0) {
            throw new InvalidArgumentException('Result cannot be negative');
        }

        return new self($result, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->cents * $factor, $this->currency);
    }

    public function applyDiscount(int $percentOff): self
    {
        if ($percentOff < 0 || $percentOff > 100) {
            throw new InvalidArgumentException('Discount percentage must be between 0 and 100');
        }

        $discountedCents = (int) round($this->cents * (100 - $percentOff) / 100);
        return new self($discountedCents, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->cents > $other->cents;
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->cents < $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents
            && $this->currency === $other->currency;
    }

    public function format(): string
    {
        return sprintf('$%.2f', $this->amount());
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot operate on different currencies: %s vs %s',
                    $this->currency,
                    $other->currency
                )
            );
        }
    }
}
