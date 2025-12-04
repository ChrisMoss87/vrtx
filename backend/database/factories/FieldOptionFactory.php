<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Field;
use App\Models\FieldOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FieldOption>
 */
class FieldOptionFactory extends Factory
{
    protected $model = FieldOption::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = $this->faker->word();

        return [
            'field_id' => Field::factory(),
            'label' => ucfirst($label),
            'value' => strtolower($label),
            'display_order' => $this->faker->numberBetween(0, 10),
            'is_active' => true,
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the option is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the option is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Add color to the option.
     */
    public function withColor(): static
    {
        return $this->state(fn (array $attributes) => [
            'color' => $this->faker->hexColor(),
        ]);
    }
}
