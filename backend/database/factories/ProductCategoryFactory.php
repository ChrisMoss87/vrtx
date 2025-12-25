<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Software Licenses',
                'Subscriptions',
                'Professional Services',
                'Support Packages',
                'Training',
                'Hardware',
                'Add-ons',
                'Consulting',
                'Implementation',
            ]) . ' ' . $this->faker->unique()->numberBetween(1, 1000),
            'parent_id' => null,
            'display_order' => $this->faker->numberBetween(1, 100),
        ];
    }

    /**
     * Software category.
     */
    public function software(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Software Licenses',
        ]);
    }

    /**
     * Services category.
     */
    public function services(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Professional Services',
        ]);
    }

    /**
     * Support category.
     */
    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Support Packages',
        ]);
    }

    /**
     * Subscriptions category.
     */
    public function subscriptions(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Subscriptions',
        ]);
    }

    /**
     * Child category.
     */
    public function childOf(int $parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }
}
