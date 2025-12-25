<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Playbook;
use App\Infrastructure\Persistence\Eloquent\Models\PlaybookInstance;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\PlaybookInstance>
 */
class PlaybookInstanceFactory extends Factory
{
    protected $model = PlaybookInstance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-60 days', '-7 days');

        return [
            'playbook_id' => Playbook::factory(),
            'related_module' => $this->faker->randomElement(['deals', 'leads', 'accounts']),
            'related_id' => $this->faker->numberBetween(1, 1000),
            'status' => $this->faker->randomElement(['active', 'completed', 'paused', 'cancelled']),
            'started_at' => $startedAt,
            'target_completion_at' => $this->faker->dateTimeBetween($startedAt, '+30 days'),
            'completed_at' => null,
            'paused_at' => null,
            'owner_id' => User::factory(),
            'progress_percent' => $this->faker->numberBetween(0, 100),
            'metadata' => [
                'deal_value' => $this->faker->numberBetween(10000, 500000),
                'account_name' => $this->faker->company(),
            ],
        ];
    }

    /**
     * Active instance state.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'completed_at' => null,
            'paused_at' => null,
            'progress_percent' => $this->faker->numberBetween(10, 80),
        ]);
    }

    /**
     * Completed instance state.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
            'progress_percent' => 100,
        ]);
    }

    /**
     * Paused instance state.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paused',
            'paused_at' => now(),
        ]);
    }

    /**
     * Cancelled instance state.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * For deals.
     */
    public function forDeal(int $dealId): static
    {
        return $this->state(fn (array $attributes) => [
            'related_module' => 'deals',
            'related_id' => $dealId,
        ]);
    }

    /**
     * With task instances.
     */
    public function withTaskInstances(int $count = 5): static
    {
        return $this->has(
            \App\Infrastructure\Persistence\Eloquent\Models\PlaybookTaskInstance::factory()->count($count),
            'taskInstances'
        );
    }
}
