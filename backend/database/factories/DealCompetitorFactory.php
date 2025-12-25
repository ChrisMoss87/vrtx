<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DealCompetitor>
 */
class DealCompetitorFactory extends Factory
{
    protected $model = DealCompetitor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deal_id' => $this->faker->numberBetween(1, 1000),
            'competitor_id' => Competitor::factory(),
            'is_primary' => $this->faker->boolean(30),
            'notes' => $this->faker->optional(0.6)->randomElement([
                'Customer is comparing pricing closely',
                'They prefer our mobile app',
                'Competitor offered aggressive discount',
                'Customer concerned about their support',
                'They have existing relationship with this vendor',
            ]),
            'outcome' => $this->faker->randomElement([
                DealCompetitor::OUTCOME_WON,
                DealCompetitor::OUTCOME_LOST,
                DealCompetitor::OUTCOME_UNKNOWN,
            ]),
        ];
    }

    /**
     * Primary competitor.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Won against this competitor.
     */
    public function won(): static
    {
        return $this->state(fn (array $attributes) => [
            'outcome' => DealCompetitor::OUTCOME_WON,
        ]);
    }

    /**
     * Lost to this competitor.
     */
    public function lost(): static
    {
        return $this->state(fn (array $attributes) => [
            'outcome' => DealCompetitor::OUTCOME_LOST,
        ]);
    }

    /**
     * Unknown outcome.
     */
    public function unknown(): static
    {
        return $this->state(fn (array $attributes) => [
            'outcome' => DealCompetitor::OUTCOME_UNKNOWN,
        ]);
    }
}
