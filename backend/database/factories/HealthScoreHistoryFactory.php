<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HealthScoreHistory>
 */
class HealthScoreHistoryFactory extends Factory
{
    protected $model = HealthScoreHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_health_score_id' => CustomerHealthScore::factory(),
            'overall_score' => $this->faker->numberBetween(20, 100),
            'scores_snapshot' => [
                'engagement' => $this->faker->numberBetween(20, 100),
                'support' => $this->faker->numberBetween(20, 100),
                'product_usage' => $this->faker->numberBetween(20, 100),
                'payment' => $this->faker->numberBetween(50, 100),
                'relationship' => $this->faker->numberBetween(30, 100),
            ],
            'recorded_at' => $this->faker->dateTimeBetween('-90 days', 'now'),
        ];
    }

    /**
     * High score snapshot.
     */
    public function highScore(): static
    {
        return $this->state(fn (array $attributes) => [
            'overall_score' => $this->faker->numberBetween(75, 100),
            'scores_snapshot' => [
                'engagement' => $this->faker->numberBetween(70, 100),
                'support' => $this->faker->numberBetween(80, 100),
                'product_usage' => $this->faker->numberBetween(75, 100),
                'payment' => $this->faker->numberBetween(90, 100),
                'relationship' => $this->faker->numberBetween(70, 100),
            ],
        ]);
    }

    /**
     * Low score snapshot.
     */
    public function lowScore(): static
    {
        return $this->state(fn (array $attributes) => [
            'overall_score' => $this->faker->numberBetween(20, 45),
            'scores_snapshot' => [
                'engagement' => $this->faker->numberBetween(10, 40),
                'support' => $this->faker->numberBetween(20, 50),
                'product_usage' => $this->faker->numberBetween(10, 40),
                'payment' => $this->faker->numberBetween(40, 70),
                'relationship' => $this->faker->numberBetween(20, 40),
            ],
        ]);
    }

    /**
     * Recent recording.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'recorded_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Historical recording (older than 30 days).
     */
    public function historical(): static
    {
        return $this->state(fn (array $attributes) => [
            'recorded_at' => $this->faker->dateTimeBetween('-90 days', '-30 days'),
        ]);
    }
}
