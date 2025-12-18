<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SupportTeam;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupportTeam>
 */
class SupportTeamFactory extends Factory
{
    protected $model = SupportTeam::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Technical Support',
                'Billing Support',
                'Customer Success',
                'Enterprise Support',
                'Product Support',
                'API Support',
                'Onboarding Team',
            ]) . ' ' . $this->faker->unique()->numberBetween(1, 100),
            'description' => $this->faker->sentence(),
            'lead_id' => User::factory(),
            'is_active' => true,
            'settings' => [
                'auto_assignment' => $this->faker->boolean(70),
                'round_robin' => $this->faker->boolean(60),
                'max_tickets_per_agent' => $this->faker->numberBetween(10, 30),
                'working_hours' => [
                    'start' => '09:00',
                    'end' => '17:00',
                    'timezone' => 'America/New_York',
                ],
                'escalation_enabled' => $this->faker->boolean(80),
            ],
        ];
    }

    /**
     * Active team.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive team.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Technical support team.
     */
    public function technical(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Technical Support',
            'description' => 'Handles technical issues and product support',
        ]);
    }

    /**
     * Billing support team.
     */
    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Billing Support',
            'description' => 'Handles billing and payment inquiries',
        ]);
    }

    /**
     * With round robin assignment.
     */
    public function withRoundRobin(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'auto_assignment' => true,
                'round_robin' => true,
            ]),
        ]);
    }
}
