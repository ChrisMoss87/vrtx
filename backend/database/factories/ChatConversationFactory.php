<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Chat\Entities\ChatConversation;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Chat\Entities\ChatConversation>
 */
class ChatConversationFactory extends Factory
{
    protected $model = ChatConversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $createdAt = $this->faker->dateTimeBetween('-7 days', 'now');
        $messageCount = $this->faker->numberBetween(2, 20);

        return [
            'widget_id' => ChatWidget::factory(),
            'visitor_id' => ChatVisitor::factory(),
            'contact_id' => null,
            'assigned_to' => User::factory(),
            'status' => $this->faker->randomElement([
                ChatConversation::STATUS_OPEN,
                ChatConversation::STATUS_PENDING,
                ChatConversation::STATUS_CLOSED,
            ]),
            'priority' => $this->faker->randomElement([
                ChatConversation::PRIORITY_LOW,
                ChatConversation::PRIORITY_NORMAL,
                ChatConversation::PRIORITY_HIGH,
            ]),
            'department' => $this->faker->randomElement(['sales', 'support', 'billing']),
            'subject' => $this->faker->optional(0.5)->sentence(4),
            'tags' => $this->faker->randomElements(['pricing', 'bug', 'feature-request', 'urgent'], 2),
            'message_count' => $messageCount,
            'visitor_message_count' => (int) ($messageCount * 0.4),
            'agent_message_count' => (int) ($messageCount * 0.6),
            'rating' => $this->faker->optional(0.3)->randomFloat(1, 1, 5),
            'rating_comment' => null,
            'first_response_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
            'resolved_at' => null,
            'last_message_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
            'created_at' => $createdAt,
        ];
    }

    /**
     * Open conversation.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChatConversation::STATUS_OPEN,
            'resolved_at' => null,
        ]);
    }

    /**
     * Pending conversation.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChatConversation::STATUS_PENDING,
            'resolved_at' => null,
        ]);
    }

    /**
     * Closed conversation.
     */
    public function closed(): static
    {
        $createdAt = $this->faker->dateTimeBetween('-14 days', '-7 days');

        return $this->state(fn (array $attributes) => [
            'status' => ChatConversation::STATUS_CLOSED,
            'resolved_at' => $this->faker->dateTimeBetween($createdAt, '-1 day'),
            'created_at' => $createdAt,
        ]);
    }

    /**
     * Unassigned conversation.
     */
    public function unassigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => null,
            'first_response_at' => null,
        ]);
    }

    /**
     * High priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => ChatConversation::PRIORITY_HIGH,
        ]);
    }

    /**
     * Urgent priority.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => ChatConversation::PRIORITY_URGENT,
        ]);
    }

    /**
     * With rating.
     */
    public function rated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChatConversation::STATUS_CLOSED,
            'rating' => $this->faker->randomFloat(1, 3, 5),
            'rating_comment' => $this->faker->optional(0.5)->sentence(),
        ]);
    }

    /**
     * Sales department.
     */
    public function sales(): static
    {
        return $this->state(fn (array $attributes) => [
            'department' => 'sales',
            'tags' => ['pricing', 'demo-request'],
        ]);
    }

    /**
     * Support department.
     */
    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'department' => 'support',
            'tags' => ['bug', 'help-needed'],
        ]);
    }
}
