<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Playbook;
use App\Infrastructure\Persistence\Eloquent\Models\PlaybookGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\PlaybookGoal>
 */
class PlaybookGoalFactory extends Factory
{
    protected $model = PlaybookGoal::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'playbook_id' => Playbook::factory(),
            'name' => $this->faker->randomElement([
                'Close Deal',
                'Increase Deal Value',
                'Complete Demo',
                'Get Contract Signed',
                'Schedule Kickoff',
                'Achieve First Value',
            ]),
            'metric_type' => $this->faker->randomElement(['field_value', 'stage_reached', 'activity_count']),
            'target_module' => 'deals',
            'target_field' => $this->faker->randomElement(['amount', 'stage', 'probability']),
            'comparison_operator' => $this->faker->randomElement(['>=', '>', '=', '<=', '<']),
            'target_value' => $this->faker->randomFloat(2, 10000, 500000),
            'target_days' => $this->faker->numberBetween(14, 60),
            'description' => $this->faker->sentence(),
        ];
    }

    /**
     * Close deal goal.
     */
    public function closeDeal(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Close Deal',
            'metric_type' => 'stage_reached',
            'target_field' => 'stage',
            'comparison_operator' => '=',
            'target_value' => 'closed_won',
            'target_days' => 30,
        ]);
    }

    /**
     * Deal value goal.
     */
    public function dealValue(float $value = 50000): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Achieve Target Value',
            'metric_type' => 'field_value',
            'target_field' => 'amount',
            'comparison_operator' => '>=',
            'target_value' => $value,
            'target_days' => 45,
        ]);
    }
}
