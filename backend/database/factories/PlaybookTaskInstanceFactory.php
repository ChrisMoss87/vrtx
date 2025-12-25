<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\PlaybookInstance;
use App\Infrastructure\Persistence\Eloquent\Models\PlaybookTask;
use App\Infrastructure\Persistence\Eloquent\Models\PlaybookTaskInstance;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\PlaybookTaskInstance>
 */
class PlaybookTaskInstanceFactory extends Factory
{
    protected $model = PlaybookTaskInstance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instance_id' => PlaybookInstance::factory(),
            'task_id' => PlaybookTask::factory(),
            'status' => $this->faker->randomElement(['pending', 'in_progress', 'completed', 'skipped', 'blocked']),
            'due_at' => $this->faker->dateTimeBetween('now', '+14 days'),
            'started_at' => null,
            'completed_at' => null,
            'assigned_to' => User::factory(),
            'completed_by' => null,
            'notes' => $this->faker->optional()->paragraph(),
            'checklist_status' => [],
            'time_spent' => null,
        ];
    }

    /**
     * Pending state.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * In progress state.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'started_at' => now(),
            'completed_at' => null,
        ]);
    }

    /**
     * Completed state.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subDays(2),
            'completed_at' => now(),
            'completed_by' => User::factory(),
            'time_spent' => $this->faker->numberBetween(15, 180),
        ]);
    }

    /**
     * Skipped state.
     */
    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'skipped',
            'completed_at' => now(),
            'notes' => 'Skipped - not applicable for this deal',
        ]);
    }

    /**
     * Blocked state.
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'blocked',
            'notes' => 'Blocked - waiting on customer response',
        ]);
    }

    /**
     * Overdue state.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'due_at' => now()->subDays(3),
        ]);
    }
}
