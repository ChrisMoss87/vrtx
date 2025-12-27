<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Entities\Product;
use App\Domain\Billing\Entities\Quote;
use App\Domain\Billing\Entities\QuoteLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Billing\Entities\QuoteLineItem>
 */
class QuoteLineItemFactory extends Factory
{
    protected $model = QuoteLineItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 20);
        $unitPrice = $this->faker->randomFloat(2, 100, 10000);
        $discountPercent = $this->faker->randomFloat(2, 0, 15);
        $lineTotal = $quantity * $unitPrice * (1 - $discountPercent / 100);

        return [
            'quote_id' => Quote::factory(),
            'product_id' => null,
            'description' => $this->faker->randomElement([
                'Software License - Annual',
                'Professional Services - Implementation',
                'Training Package - 5 sessions',
                'Support Plan - Premium',
                'Custom Development - 40 hours',
                'Data Migration Services',
                'API Integration Package',
            ]),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_percent' => $discountPercent,
            'tax_rate' => 8.5,
            'line_total' => $lineTotal,
            'display_order' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * With product.
     */
    public function withProduct(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => Product::factory(),
        ]);
    }

    /**
     * No discount.
     */
    public function noDiscount(): static
    {
        return $this->state(function (array $attributes) {
            $lineTotal = $attributes['quantity'] * $attributes['unit_price'];
            return [
                'discount_percent' => 0,
                'line_total' => $lineTotal,
            ];
        });
    }

    /**
     * High quantity.
     */
    public function highQuantity(): static
    {
        return $this->state(function (array $attributes) {
            $quantity = $this->faker->numberBetween(50, 200);
            $unitPrice = $attributes['unit_price'];
            $discountPercent = $attributes['discount_percent'];
            $lineTotal = $quantity * $unitPrice * (1 - $discountPercent / 100);

            return [
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];
        });
    }
}
