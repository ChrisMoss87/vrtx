<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CadenceStepExecution>
 */
class CadenceStepExecutionFactory extends Factory
{
    protected $model = CadenceStepExecution::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => CadenceEnrollment::factory(),
            'step_id' => CadenceStep::factory(),
            'scheduled_at' => $this->faker->dateTimeBetween('-7 days', '+7 days'),
            'executed_at' => null,
            'status' => CadenceStepExecution::STATUS_SCHEDULED,
            'result' => null,
            'error_message' => null,
            'metadata' => [],
        ];
    }

    /**
     * Completed execution state.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CadenceStepExecution::STATUS_COMPLETED,
            'executed_at' => now(),
            'result' => $this->faker->randomElement([
                CadenceStepExecution::RESULT_SENT,
                CadenceStepExecution::RESULT_DELIVERED,
                CadenceStepExecution::RESULT_OPENED,
                CadenceStepExecution::RESULT_CLICKED,
            ]),
        ]);
    }

    /**
     * Failed execution state.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CadenceStepExecution::STATUS_FAILED,
            'executed_at' => now(),
            'result' => CadenceStepExecution::RESULT_FAILED,
            'error_message' => $this->faker->randomElement([
                'Email bounced - invalid address',
                'Rate limit exceeded',
                'Connection timeout',
            ]),
        ]);
    }

    /**
     * Email opened state.
     */
    public function opened(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CadenceStepExecution::STATUS_COMPLETED,
            'executed_at' => now(),
            'result' => CadenceStepExecution::RESULT_OPENED,
            'metadata' => [
                'opened_at' => now()->toISOString(),
                'user_agent' => 'Mozilla/5.0',
            ],
        ]);
    }

    /**
     * Email clicked state.
     */
    public function clicked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CadenceStepExecution::STATUS_COMPLETED,
            'executed_at' => now(),
            'result' => CadenceStepExecution::RESULT_CLICKED,
            'metadata' => [
                'clicked_at' => now()->toISOString(),
                'link_url' => 'https://example.com/resource',
            ],
        ]);
    }

    /**
     * Replied state.
     */
    public function replied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CadenceStepExecution::STATUS_COMPLETED,
            'executed_at' => now(),
            'result' => CadenceStepExecution::RESULT_REPLIED,
        ]);
    }
}
