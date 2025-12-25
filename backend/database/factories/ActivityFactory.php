<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement([
                Activity::TYPE_NOTE,
                Activity::TYPE_CALL,
                Activity::TYPE_MEETING,
                Activity::TYPE_TASK,
                Activity::TYPE_EMAIL,
            ]),
            'action' => Activity::ACTION_CREATED,
            'subject_type' => ModuleRecord::class,
            'subject_id' => ModuleRecord::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'metadata' => [],
            'content' => $this->faker->paragraphs(2, true),
            'is_pinned' => false,
            'is_internal' => false,
            'is_system' => false,
            'scheduled_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Create a note activity.
     */
    public function note(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Activity::TYPE_NOTE,
            'title' => 'Note: ' . $this->faker->sentence(3),
        ]);
    }

    /**
     * Create a call activity.
     */
    public function call(?string $outcome = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Activity::TYPE_CALL,
            'title' => 'Call: ' . $this->faker->sentence(3),
            'duration_minutes' => $this->faker->numberBetween(5, 60),
            'outcome' => $outcome ?? $this->faker->randomElement([
                Activity::OUTCOME_COMPLETED,
                Activity::OUTCOME_NO_ANSWER,
                Activity::OUTCOME_LEFT_VOICEMAIL,
            ]),
        ]);
    }

    /**
     * Create a meeting activity.
     */
    public function meeting(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Activity::TYPE_MEETING,
            'title' => 'Meeting: ' . $this->faker->sentence(3),
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+1 week'),
            'duration_minutes' => $this->faker->randomElement([30, 45, 60, 90]),
        ]);
    }

    /**
     * Create a task activity.
     */
    public function task(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Activity::TYPE_TASK,
            'title' => 'Task: ' . $this->faker->sentence(3),
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+1 week'),
        ]);
    }

    /**
     * Create an email activity.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Activity::TYPE_EMAIL,
            'title' => 'Email: ' . $this->faker->sentence(3),
            'action' => Activity::ACTION_SENT,
        ]);
    }

    /**
     * Mark activity as pinned.
     */
    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => true,
        ]);
    }

    /**
     * Mark activity as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
            'outcome' => Activity::OUTCOME_COMPLETED,
        ]);
    }

    /**
     * Create a scheduled activity.
     */
    public function scheduled(\DateTime $at = null): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => $at ?? $this->faker->dateTimeBetween('now', '+1 week'),
        ]);
    }

    /**
     * Create an overdue activity.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => $this->faker->dateTimeBetween('-1 week', '-1 day'),
            'completed_at' => null,
        ]);
    }

    /**
     * Mark as system activity.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * Mark as internal activity.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
        ]);
    }
}
