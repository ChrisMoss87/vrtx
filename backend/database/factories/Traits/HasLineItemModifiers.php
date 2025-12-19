<?php

declare(strict_types=1);

namespace Database\Factories\Traits;

/**
 * Common modifiers for line item factories (Quote, Invoice, Contract line items).
 */
trait HasLineItemModifiers
{
    /**
     * Apply a discount percentage.
     */
    public function withDiscount(?int $percent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_percent' => $percent ?? $this->faker->numberBetween(5, 25),
        ]);
    }

    /**
     * Remove any discount.
     */
    public function noDiscount(): static
    {
        return $this->state(function (array $attributes) {
            $lineTotal = ($attributes['quantity'] ?? 1) * ($attributes['unit_price'] ?? 0);
            return [
                'discount_percent' => 0,
                'line_total' => $lineTotal,
                'total' => $lineTotal,
            ];
        });
    }

    /**
     * Apply a tax rate.
     */
    public function withTax(?int $rate = null): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_rate' => $rate ?? 8,
        ]);
    }

    /**
     * Remove tax.
     */
    public function noTax(): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_rate' => 0,
        ]);
    }

    /**
     * High quantity order.
     */
    public function highQuantity(?int $quantity = null): static
    {
        return $this->state(function (array $attributes) use ($quantity) {
            $qty = $quantity ?? $this->faker->numberBetween(50, 200);
            $unitPrice = $attributes['unit_price'] ?? 100;
            $discountPercent = $attributes['discount_percent'] ?? 0;
            $lineTotal = $qty * $unitPrice * (1 - $discountPercent / 100);

            return [
                'quantity' => $qty,
                'line_total' => $lineTotal,
                'total' => $lineTotal,
            ];
        });
    }

    /**
     * Premium pricing.
     */
    public function premium(): static
    {
        return $this->state(function (array $attributes) {
            $unitPrice = $this->faker->randomFloat(2, 5000, 25000);
            $quantity = $attributes['quantity'] ?? 1;
            $discountPercent = $attributes['discount_percent'] ?? 0;
            $lineTotal = $quantity * $unitPrice * (1 - $discountPercent / 100);

            return [
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'total' => $lineTotal,
            ];
        });
    }
}
