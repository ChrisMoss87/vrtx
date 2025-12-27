<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Approval\Entities\ApprovalNotification;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Approval\Entities\ApprovalNotification>
 */
class ApprovalNotificationFactory extends Factory
{
    protected $model = ApprovalNotification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => ApprovalRequest::factory(),
            'step_id' => ApprovalStep::factory(),
            'user_id' => User::factory(),
            'notification_type' => $this->faker->randomElement(ApprovalNotification::TYPES),
            'channel' => $this->faker->randomElement(ApprovalNotification::CHANNELS),
            'status' => ApprovalNotification::STATUS_PENDING,
            'scheduled_at' => now(),
            'sent_at' => null,
        ];
    }

    /**
     * Pending notification.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_type' => ApprovalNotification::TYPE_PENDING,
            'status' => ApprovalNotification::STATUS_PENDING,
        ]);
    }

    /**
     * Reminder notification.
     */
    public function reminder(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_type' => ApprovalNotification::TYPE_REMINDER,
        ]);
    }

    /**
     * Escalation notification.
     */
    public function escalation(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_type' => ApprovalNotification::TYPE_ESCALATION,
        ]);
    }

    /**
     * Sent status.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalNotification::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Failed status.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalNotification::STATUS_FAILED,
        ]);
    }

    /**
     * Email channel.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => ApprovalNotification::CHANNEL_EMAIL,
        ]);
    }

    /**
     * In-app channel.
     */
    public function inApp(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => ApprovalNotification::CHANNEL_IN_APP,
        ]);
    }
}
