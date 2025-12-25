<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlueprintSlaEscalation>
 */
class BlueprintSlaEscalationFactory extends Factory
{
    protected $model = BlueprintSlaEscalation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sla_id' => BlueprintSla::factory(),
            'trigger_type' => $this->faker->randomElement([
                BlueprintSlaEscalation::TRIGGER_APPROACHING,
                BlueprintSlaEscalation::TRIGGER_BREACHED,
            ]),
            'trigger_value' => 80,
            'action_type' => $this->faker->randomElement([
                BlueprintSlaEscalation::ACTION_SEND_EMAIL,
                BlueprintSlaEscalation::ACTION_NOTIFY_USER,
                BlueprintSlaEscalation::ACTION_CREATE_TASK,
            ]),
            'config' => [
                'recipients' => ['owner', 'manager'],
                'template_id' => 1,
            ],
            'display_order' => $this->faker->numberBetween(1, 5),
        ];
    }

    /**
     * Approaching trigger.
     */
    public function approaching(int $percentage = 80): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => BlueprintSlaEscalation::TRIGGER_APPROACHING,
            'trigger_value' => $percentage,
        ]);
    }

    /**
     * Breached trigger.
     */
    public function breached(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => BlueprintSlaEscalation::TRIGGER_BREACHED,
            'trigger_value' => null,
        ]);
    }

    /**
     * Send email action.
     */
    public function sendEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => BlueprintSlaEscalation::ACTION_SEND_EMAIL,
            'config' => [
                'template_id' => 1,
                'recipients' => ['owner', 'manager'],
            ],
        ]);
    }

    /**
     * Notify user action.
     */
    public function notifyUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => BlueprintSlaEscalation::ACTION_NOTIFY_USER,
            'config' => [
                'user_ids' => [1],
                'message' => 'SLA is at risk',
            ],
        ]);
    }
}
