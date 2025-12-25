<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Proposal;
use App\Infrastructure\Persistence\Eloquent\Models\ProposalView;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\ProposalView>
 */
class ProposalViewFactory extends Factory
{
    protected $model = ProposalView::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-7 days', 'now');

        return [
            'proposal_id' => Proposal::factory(),
            'viewer_email' => $this->faker->optional(0.7)->email(),
            'viewer_name' => $this->faker->optional(0.5)->name(),
            'session_id' => Str::uuid()->toString(),
            'started_at' => $startedAt,
            'ended_at' => $this->faker->optional(0.8)->dateTimeBetween($startedAt, 'now'),
            'time_spent_seconds' => $this->faker->numberBetween(30, 600),
            'section_views' => [
                ['section_id' => 1, 'time_spent' => 45],
                ['section_id' => 2, 'time_spent' => 120],
                ['section_id' => 3, 'time_spent' => 90],
            ],
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }

    /**
     * Short view session.
     */
    public function brief(): static
    {
        return $this->state(fn (array $attributes) => [
            'time_spent_seconds' => $this->faker->numberBetween(10, 60),
        ]);
    }

    /**
     * Engaged view session.
     */
    public function engaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'time_spent_seconds' => $this->faker->numberBetween(300, 900),
            'section_views' => [
                ['section_id' => 1, 'time_spent' => 60],
                ['section_id' => 2, 'time_spent' => 180],
                ['section_id' => 3, 'time_spent' => 240],
                ['section_id' => 4, 'time_spent' => 150],
            ],
        ]);
    }

    /**
     * Identified viewer.
     */
    public function identified(): static
    {
        return $this->state(fn (array $attributes) => [
            'viewer_email' => $this->faker->email(),
            'viewer_name' => $this->faker->name(),
        ]);
    }
}
