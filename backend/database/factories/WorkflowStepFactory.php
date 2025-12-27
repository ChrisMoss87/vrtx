<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Workflow\Entities\WorkflowStep;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Workflow\Entities\WorkflowStep>
 */
class WorkflowStepFactory extends Factory
{
    protected $model = WorkflowStep::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'order' => $this->faker->numberBetween(0, 10),
            'name' => $this->faker->words(2, true),
            'action_type' => $this->faker->randomElement([
                WorkflowStep::ACTION_SEND_EMAIL,
                WorkflowStep::ACTION_UPDATE_FIELD,
                WorkflowStep::ACTION_SEND_NOTIFICATION,
                WorkflowStep::ACTION_CREATE_TASK,
            ]),
            'action_config' => [],
            'conditions' => null,
            'is_parallel' => false,
            'continue_on_error' => false,
            'retry_count' => 0,
            'retry_delay_seconds' => 60,
        ];
    }

    /**
     * Create a send email step.
     */
    public function sendEmail(array $config = []): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => WorkflowStep::ACTION_SEND_EMAIL,
            'action_config' => array_merge([
                'to' => '{{record.email}}',
                'subject' => 'Notification',
                'body' => 'This is an automated email.',
            ], $config),
        ]);
    }

    /**
     * Create an update field step.
     */
    public function updateField(string $field = 'status', $value = 'updated'): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => WorkflowStep::ACTION_UPDATE_FIELD,
            'action_config' => [
                'field' => $field,
                'value' => $value,
            ],
        ]);
    }

    /**
     * Create a create task step.
     */
    public function createTask(array $config = []): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => WorkflowStep::ACTION_CREATE_TASK,
            'action_config' => array_merge([
                'title' => 'Follow up',
                'due_days' => 3,
            ], $config),
        ]);
    }

    /**
     * Create a send notification step.
     */
    public function sendNotification(array $config = []): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => WorkflowStep::ACTION_SEND_NOTIFICATION,
            'action_config' => array_merge([
                'message' => 'Workflow notification',
                'recipients' => ['owner'],
            ], $config),
        ]);
    }

    /**
     * Create a webhook step.
     */
    public function webhook(string $url = 'https://example.com/webhook'): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => WorkflowStep::ACTION_WEBHOOK,
            'action_config' => [
                'url' => $url,
                'method' => 'POST',
                'headers' => [],
            ],
        ]);
    }

    /**
     * Create a delay step.
     */
    public function delay(int $seconds = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => WorkflowStep::ACTION_DELAY,
            'action_config' => [
                'delay_seconds' => $seconds,
            ],
        ]);
    }

    /**
     * Create a move stage step.
     */
    public function moveStage(string $stage = 'Qualified'): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => WorkflowStep::ACTION_MOVE_STAGE,
            'action_config' => [
                'stage' => $stage,
            ],
        ]);
    }

    /**
     * Allow step to continue on error.
     */
    public function continueOnError(): static
    {
        return $this->state(fn (array $attributes) => [
            'continue_on_error' => true,
        ]);
    }

    /**
     * Set retry count.
     */
    public function withRetries(int $count = 3, int $delaySeconds = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'retry_count' => $count,
            'retry_delay_seconds' => $delaySeconds,
        ]);
    }
}
