<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Blueprint\Entities\BlueprintSlaEscalationLog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Blueprint\Entities\BlueprintSlaEscalationLog>
 */
class BlueprintSlaEscalationLogFactory extends Factory
{
    protected $model = BlueprintSlaEscalationLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sla_instance_id' => BlueprintSlaInstance::factory(),
            'escalation_id' => BlueprintSlaEscalation::factory(),
            'executed_at' => now(),
            'status' => $this->faker->randomElement(['success', 'failed']),
            'result' => [
                'message' => 'Escalation action executed',
            ],
        ];
    }

    /**
     * Success status.
     */
    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'result' => [
                'message' => 'Email sent successfully',
                'recipients' => ['manager@example.com'],
            ],
        ]);
    }

    /**
     * Failed status.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'result' => [
                'error' => 'Failed to send notification',
                'reason' => 'User email not configured',
            ],
        ]);
    }
}
