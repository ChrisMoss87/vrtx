<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Cadence\Entities\CadenceMetric;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Cadence\Entities\CadenceMetric>
 */
class CadenceMetricFactory extends Factory
{
    protected $model = CadenceMetric::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cadence_id' => Cadence::factory(),
            'step_id' => null,
            'date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'enrollments' => $this->faker->numberBetween(5, 50),
            'completions' => $this->faker->numberBetween(0, 20),
            'replies' => $this->faker->numberBetween(0, 10),
            'meetings_booked' => $this->faker->numberBetween(0, 5),
            'bounces' => $this->faker->numberBetween(0, 3),
            'unsubscribes' => $this->faker->numberBetween(0, 2),
            'emails_sent' => $this->faker->numberBetween(10, 100),
            'emails_opened' => $this->faker->numberBetween(5, 50),
            'emails_clicked' => $this->faker->numberBetween(2, 20),
            'calls_made' => $this->faker->numberBetween(0, 30),
            'calls_connected' => $this->faker->numberBetween(0, 15),
            'sms_sent' => $this->faker->numberBetween(0, 20),
            'sms_replied' => $this->faker->numberBetween(0, 5),
        ];
    }

    /**
     * For specific step.
     */
    public function forStep(CadenceStep $step): static
    {
        return $this->state(fn (array $attributes) => [
            'step_id' => $step->id,
        ]);
    }

    /**
     * High performing metrics.
     */
    public function highPerforming(): static
    {
        return $this->state(fn (array $attributes) => [
            'emails_sent' => 100,
            'emails_opened' => 75,
            'emails_clicked' => 40,
            'replies' => 25,
            'meetings_booked' => 10,
            'bounces' => 1,
            'unsubscribes' => 0,
        ]);
    }

    /**
     * Today's metrics.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => now()->toDateString(),
        ]);
    }
}
