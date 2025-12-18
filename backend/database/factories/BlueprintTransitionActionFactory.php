<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BlueprintTransition;
use App\Models\BlueprintTransitionAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlueprintTransitionAction>
 */
class BlueprintTransitionActionFactory extends Factory
{
    protected $model = BlueprintTransitionAction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transition_id' => BlueprintTransition::factory(),
            'type' => $this->faker->randomElement([
                BlueprintTransitionAction::TYPE_SEND_EMAIL,
                BlueprintTransitionAction::TYPE_UPDATE_FIELD,
                BlueprintTransitionAction::TYPE_CREATE_TASK,
                BlueprintTransitionAction::TYPE_NOTIFY_USER,
            ]),
            'config' => [],
            'display_order' => $this->faker->numberBetween(1, 5),
            'is_active' => true,
        ];
    }

    /**
     * Send email action.
     */
    public function sendEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BlueprintTransitionAction::TYPE_SEND_EMAIL,
            'config' => [
                'template_id' => 1,
                'to' => ['owner'],
                'subject' => 'Record transitioned',
            ],
        ]);
    }

    /**
     * Update field action.
     */
    public function updateField(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BlueprintTransitionAction::TYPE_UPDATE_FIELD,
            'config' => [
                'field_id' => 1,
                'value' => 'Updated via transition',
            ],
        ]);
    }

    /**
     * Create task action.
     */
    public function createTask(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BlueprintTransitionAction::TYPE_CREATE_TASK,
            'config' => [
                'title' => 'Follow up on transition',
                'due_days' => 3,
                'assignee' => 'owner',
            ],
        ]);
    }

    /**
     * Webhook action.
     */
    public function webhook(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BlueprintTransitionAction::TYPE_WEBHOOK,
            'config' => [
                'url' => 'https://api.example.com/webhook',
                'method' => 'POST',
                'headers' => ['Authorization' => 'Bearer token'],
            ],
        ]);
    }

    /**
     * Slack message action.
     */
    public function slackMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BlueprintTransitionAction::TYPE_SLACK_MESSAGE,
            'config' => [
                'channel' => '#sales',
                'message' => 'Record {{record_name}} has been moved to {{to_state}}',
            ],
        ]);
    }
}
