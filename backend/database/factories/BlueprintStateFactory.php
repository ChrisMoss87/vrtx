<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlueprintState>
 */
class BlueprintStateFactory extends Factory
{
    protected $model = BlueprintState::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blueprint_id' => Blueprint::factory(),
            'name' => $this->faker->randomElement(['Draft', 'Pending', 'In Review', 'Approved', 'Rejected', 'Active', 'Closed']),
            'color' => $this->faker->hexColor(),
            'description' => $this->faker->sentence(),
            'is_initial' => false,
            'is_terminal' => false,
            'display_order' => $this->faker->numberBetween(0, 10),
        ];
    }

    /**
     * Mark as initial state.
     */
    public function initial(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_initial' => true,
            'is_terminal' => false,
            'name' => 'Draft',
            'color' => '#94a3b8',
        ]);
    }

    /**
     * Mark as terminal state.
     */
    public function terminal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_initial' => false,
            'is_terminal' => true,
        ]);
    }

    /**
     * Create an approved terminal state.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Approved',
            'color' => '#22c55e',
            'is_terminal' => true,
        ]);
    }

    /**
     * Create a rejected terminal state.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Rejected',
            'color' => '#ef4444',
            'is_terminal' => true,
        ]);
    }

    /**
     * Create a pending state.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Pending Approval',
            'color' => '#f59e0b',
            'is_initial' => false,
            'is_terminal' => false,
        ]);
    }
}
