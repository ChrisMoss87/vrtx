<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlaybookGoal;
use App\Models\PlaybookGoalResult;
use App\Models\PlaybookInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlaybookGoalResult>
 */
class PlaybookGoalResultFactory extends Factory
{
    protected $model = PlaybookGoalResult::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instance_id' => PlaybookInstance::factory(),
            'goal_id' => PlaybookGoal::factory(),
            'actual_value' => $this->faker->randomFloat(2, 10000, 500000),
            'achieved' => $this->faker->boolean(60),
            'achieved_at' => $this->faker->optional(0.6)->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Achieved state.
     */
    public function achieved(): static
    {
        return $this->state(fn (array $attributes) => [
            'achieved' => true,
            'achieved_at' => now(),
        ]);
    }

    /**
     * Not achieved state.
     */
    public function notAchieved(): static
    {
        return $this->state(fn (array $attributes) => [
            'achieved' => false,
            'achieved_at' => null,
        ]);
    }
}
