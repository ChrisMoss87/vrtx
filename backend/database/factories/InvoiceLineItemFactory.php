<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Entities\InvoiceLineItem;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Billing\Entities\InvoiceLineItem>
 */
class InvoiceLineItemFactory extends Factory
{
    protected $model = InvoiceLineItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 10);
        $unitPrice = $this->faker->randomFloat(2, 50, 5000);
        $discountPercent = $this->faker->boolean(30) ? $this->faker->numberBetween(5, 20) : 0;
        $subtotal = $quantity * $unitPrice;
        $lineTotal = $subtotal - ($subtotal * $discountPercent / 100);

        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => $this->faker->optional(0.5)->passthrough(Product::factory()),
            'description' => $this->faker->randomElement([
                'Professional Services - Implementation',
                'Software License (Annual)',
                'Consulting Hours',
                'Technical Support Package',
                'Training Sessions',
                'Custom Development',
                'Monthly Subscription',
            ]),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_percent' => $discountPercent,
            'tax_rate' => $this->faker->randomElement([0, 5, 8, 10]),
            'line_total' => $lineTotal,
            'display_order' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * Service line item.
     */
    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => 'Professional Services',
            'quantity' => $this->faker->numberBetween(1, 40),
            'unit_price' => $this->faker->randomElement([150, 175, 200, 250]),
        ]);
    }

    /**
     * License line item.
     */
    public function license(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => 'Software License (Annual)',
            'quantity' => 1,
            'unit_price' => $this->faker->randomFloat(2, 1000, 10000),
        ]);
    }

    /**
     * Subscription line item.
     */
    public function subscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => 'Monthly Subscription',
            'quantity' => 1,
            'unit_price' => $this->faker->randomElement([99, 199, 299, 499]),
        ]);
    }

    /**
     * With discount.
     */
    public function withDiscount(int $percent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_percent' => $percent ?? $this->faker->numberBetween(5, 25),
        ]);
    }

    /**
     * Without discount.
     */
    public function noDiscount(): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_percent' => 0,
        ]);
    }

    /**
     * With tax.
     */
    public function withTax(int $rate = null): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_rate' => $rate ?? 8,
        ]);
    }
}
