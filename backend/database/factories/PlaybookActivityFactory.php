<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlaybookActivity;
use App\Models\PlaybookInstance;
use App\Models\PlaybookTaskInstance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlaybookActivity>
 */
class PlaybookActivityFactory extends Factory
{
    protected $model = PlaybookActivity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instance_id' => PlaybookInstance::factory(),
            'task_instance_id' => null,
            'action' => $this->faker->randomElement([
                'playbook_started',
                'task_started',
                'task_completed',
                'task_skipped',
                'note_added',
                'playbook_paused',
                'playbook_resumed',
                'playbook_completed',
            ]),
            'details' => [
                'note' => $this->faker->sentence(),
            ],
            'user_id' => User::factory(),
        ];
    }

    /**
     * Playbook started action.
     */
    public function playbookStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'playbook_started',
            'details' => [
                'playbook_name' => 'Enterprise Deal Playbook',
            ],
        ]);
    }

    /**
     * Task completed action.
     */
    public function taskCompleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'task_completed',
            'task_instance_id' => PlaybookTaskInstance::factory(),
            'details' => [
                'task_name' => 'Discovery Call',
                'time_spent' => 45,
            ],
        ]);
    }

    /**
     * Note added action.
     */
    public function noteAdded(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'note_added',
            'details' => [
                'note' => $this->faker->paragraph(),
            ],
        ]);
    }
}
