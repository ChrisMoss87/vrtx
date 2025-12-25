<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GoalMilestone>
 */
class GoalMilestoneFactory extends Factory
{
    protected $model = GoalMilestone::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isAchieved = $this->faker->boolean(40);

        return [
            'goal_id' => Goal::factory(),
            'name' => $this->faker->randomElement([
                '25% Complete',
                '50% Complete',
                '75% Complete',
                'Halfway Mark',
                'First Quarter',
                'Stretch Goal',
            ]),
            'target_value' => $this->faker->randomFloat(2, 10000, 100000),
            'target_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'is_achieved' => $isAchieved,
            'achieved_at' => $isAchieved ? $this->faker->dateTimeBetween('-14 days', 'now') : null,
            'display_order' => $this->faker->numberBetween(1, 5),
        ];
    }

    /**
     * Achieved milestone.
     */
    public function achieved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_achieved' => true,
            'achieved_at' => $this->faker->dateTimeBetween('-14 days', 'now'),
        ]);
    }

    /**
     * Pending milestone.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_achieved' => false,
            'achieved_at' => null,
        ]);
    }

    /**
     * First milestone (25%).
     */
    public function firstQuarter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => '25% Complete',
            'display_order' => 1,
        ]);
    }

    /**
     * Halfway milestone (50%).
     */
    public function halfway(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Halfway Mark',
            'display_order' => 2,
        ]);
    }

    /**
     * Third milestone (75%).
     */
    public function thirdQuarter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => '75% Complete',
            'display_order' => 3,
        ]);
    }

    /**
     * Stretch goal milestone.
     */
    public function stretch(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Stretch Goal',
            'display_order' => 4,
        ]);
    }
}
