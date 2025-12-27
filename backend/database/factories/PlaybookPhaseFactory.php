<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Playbook\Entities\Playbook;
use App\Domain\Playbook\Entities\PlaybookPhase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Playbook\Entities\PlaybookPhase>
 */
class PlaybookPhaseFactory extends Factory
{
    protected $model = PlaybookPhase::class;

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
                'Discovery',
                'Qualification',
                'Solution Presentation',
                'Proposal',
                'Negotiation',
                'Closing',
                'Onboarding',
                'Implementation',
                'Follow-up',
            ]),
            'description' => $this->faker->sentence(),
            'target_days' => $this->faker->numberBetween(3, 14),
            'display_order' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * Discovery phase.
     */
    public function discovery(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Discovery',
            'description' => 'Initial discovery and needs assessment',
            'target_days' => 5,
            'display_order' => 1,
        ]);
    }

    /**
     * Qualification phase.
     */
    public function qualification(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Qualification',
            'description' => 'Qualify the opportunity using BANT criteria',
            'target_days' => 7,
            'display_order' => 2,
        ]);
    }

    /**
     * Closing phase.
     */
    public function closing(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Closing',
            'description' => 'Final negotiations and contract signing',
            'target_days' => 10,
            'display_order' => 5,
        ]);
    }
}
