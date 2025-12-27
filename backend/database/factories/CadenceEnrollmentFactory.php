<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Cadence\Entities\CadenceEnrollment;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Cadence\Entities\CadenceEnrollment>
 */
class CadenceEnrollmentFactory extends Factory
{
    protected $model = CadenceEnrollment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cadence_id' => Cadence::factory(),
            'record_id' => ModuleRecord::factory(),
            'current_step_id' => null,
            'status' => $this->faker->randomElement([
                CadenceEnrollment::STATUS_ACTIVE,
                CadenceEnrollment::STATUS_COMPLETED,
                CadenceEnrollment::STATUS_PAUSED,
            ]),
            'enrolled_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'next_step_at' => $this->faker->dateTimeBetween('now', '+7 days'),
            'completed_at' => null,
            'paused_at' => null,
            'exit_reason' => null,
            'enrolled_by' => User::factory(),
            'metadata' => [],
        ];
    }

    /**
     * Active enrollment state.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CadenceEnrollment::STATUS_ACTIVE,
            'completed_at' => null,
            'paused_at' => null,
        ]);
    }

    /**
     * Completed enrollment state.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CadenceEnrollment::STATUS_COMPLETED,
            'completed_at' => now(),
            'exit_reason' => 'Sequence completed',
        ]);
    }

    /**
     * Paused enrollment state.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CadenceEnrollment::STATUS_PAUSED,
            'paused_at' => now(),
        ]);
    }

    /**
     * Replied enrollment state.
     */
    public function replied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CadenceEnrollment::STATUS_REPLIED,
            'completed_at' => now(),
            'exit_reason' => 'Contact replied to email',
        ]);
    }

    /**
     * Meeting booked state.
     */
    public function meetingBooked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CadenceEnrollment::STATUS_MEETING_BOOKED,
            'completed_at' => now(),
            'exit_reason' => 'Meeting scheduled',
        ]);
    }

    /**
     * Due for next step.
     */
    public function due(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CadenceEnrollment::STATUS_ACTIVE,
            'next_step_at' => now()->subHours(1),
        ]);
    }
}
