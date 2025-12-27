<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Entities\Product;
use App\Domain\Billing\Entities\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Billing\Entities\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $products = [
            ['name' => 'Basic Plan', 'price' => 99, 'unit' => 'month'],
            ['name' => 'Professional Plan', 'price' => 299, 'unit' => 'month'],
            ['name' => 'Enterprise Plan', 'price' => 999, 'unit' => 'month'],
            ['name' => 'User Seat License', 'price' => 25, 'unit' => 'user'],
            ['name' => 'API Access', 'price' => 500, 'unit' => 'month'],
            ['name' => 'Data Storage (10GB)', 'price' => 50, 'unit' => 'month'],
            ['name' => 'Premium Support', 'price' => 200, 'unit' => 'month'],
            ['name' => 'Onboarding Package', 'price' => 2500, 'unit' => 'one-time'],
            ['name' => 'Training Session', 'price' => 500, 'unit' => 'session'],
            ['name' => 'Custom Integration', 'price' => 5000, 'unit' => 'project'],
            ['name' => 'Consulting Hour', 'price' => 200, 'unit' => 'hour'],
        ];

        $selected = $this->faker->randomElement($products);

        return [
            'name' => $selected['name'] . ' ' . $this->faker->unique()->numberBetween(1, 1000),
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-????-####')),
            'description' => $this->faker->sentence(),
            'unit_price' => $selected['price'],
            'currency' => 'USD',
            'tax_rate' => $this->faker->randomElement([0, 5, 8, 10]),
            'is_active' => true,
            'category_id' => ProductCategory::factory(),
            'unit' => $selected['unit'],
            'settings' => [],
        ];
    }

    /**
     * Active product.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive product.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Subscription product.
     */
    public function subscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement(['Basic', 'Pro', 'Enterprise']) . ' Plan',
            'unit' => 'month',
            'unit_price' => $this->faker->randomElement([99, 199, 299, 499, 999]),
        ]);
    }

    /**
     * Service product.
     */
    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement(['Consulting', 'Training', 'Implementation']) . ' Services',
            'unit' => 'hour',
            'unit_price' => $this->faker->randomElement([150, 175, 200, 250]),
        ]);
    }

    /**
     * One-time product.
     */
    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement(['Setup Fee', 'Onboarding', 'Custom Development']),
            'unit' => 'one-time',
            'unit_price' => $this->faker->randomFloat(2, 1000, 10000),
        ]);
    }

    /**
     * Premium/expensive product.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Enterprise ' . $this->faker->randomElement(['License', 'Solution', 'Package']),
            'unit_price' => $this->faker->randomFloat(2, 10000, 100000),
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

    /**
     * Tax exempt.
     */
    public function taxExempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_rate' => 0,
        ]);
    }
}
