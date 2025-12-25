<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlueprintTransition>
 */
class BlueprintTransitionFactory extends Factory
{
    protected $model = BlueprintTransition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blueprint_id' => Blueprint::factory(),
            'from_state_id' => BlueprintState::factory(),
            'to_state_id' => BlueprintState::factory(),
            'name' => $this->faker->randomElement(['Submit', 'Approve', 'Reject', 'Review', 'Close', 'Reopen']),
            'description' => $this->faker->sentence(),
            'button_label' => null,
            'button_color' => null,
            'is_active' => true,
            'display_order' => $this->faker->numberBetween(0, 10),
        ];
    }

    /**
     * Mark transition as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Mark transition as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set custom button styling.
     */
    public function withButton(string $label, string $color = '#3b82f6'): static
    {
        return $this->state(fn (array $attributes) => [
            'button_label' => $label,
            'button_color' => $color,
        ]);
    }

    /**
     * Create an approval transition.
     */
    public function approval(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Approve',
            'button_label' => 'Approve',
            'button_color' => '#22c55e',
        ]);
    }

    /**
     * Create a rejection transition.
     */
    public function rejection(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Reject',
            'button_label' => 'Reject',
            'button_color' => '#ef4444',
        ]);
    }
}
