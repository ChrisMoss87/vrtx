<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Contract\Entities\Contract;
use App\Domain\Contract\Entities\ContractLineItem;
use App\Domain\Billing\Entities\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Contract\Entities\ContractLineItem>
 */
class ContractLineItemFactory extends Factory
{
    protected $model = ContractLineItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 100);
        $unitPrice = $this->faker->randomFloat(2, 100, 5000);
        $discountPercent = $this->faker->boolean(30) ? $this->faker->numberBetween(5, 20) : 0;
        $subtotal = $quantity * $unitPrice;
        $total = $subtotal - ($subtotal * $discountPercent / 100);

        return [
            'contract_id' => Contract::factory(),
            'product_id' => $this->faker->optional(0.5)->passthrough(Product::factory()),
            'name' => $this->faker->randomElement([
                'Software License',
                'Support Package',
                'User Seats',
                'API Access',
                'Storage Addon',
                'Training Services',
            ]),
            'description' => $this->faker->sentence(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_percent' => $discountPercent,
            'total' => $total,
            'start_date' => $this->faker->dateTimeBetween('now', '+1 month'),
            'end_date' => $this->faker->dateTimeBetween('+6 months', '+12 months'),
            'display_order' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * License item.
     */
    public function license(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Software License',
            'quantity' => 1,
            'unit_price' => $this->faker->randomFloat(2, 5000, 50000),
        ]);
    }

    /**
     * User seats item.
     */
    public function userSeats(int $seats = null): static
    {
        $seatCount = $seats ?? $this->faker->numberBetween(5, 100);
        return $this->state(fn (array $attributes) => [
            'name' => 'User Seats',
            'quantity' => $seatCount,
            'unit_price' => $this->faker->randomElement([25, 50, 75, 100]),
        ]);
    }

    /**
     * Support package item.
     */
    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement(['Basic Support', 'Premium Support', 'Enterprise Support']),
            'quantity' => 1,
            'unit_price' => $this->faker->randomFloat(2, 1000, 10000),
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
}
