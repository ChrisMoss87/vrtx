<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApprovalHistory;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalHistory>
 */
class ApprovalHistoryFactory extends Factory
{
    protected $model = ApprovalHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => ApprovalRequest::factory(),
            'step_id' => null,
            'user_id' => User::factory(),
            'action' => $this->faker->randomElement([
                ApprovalHistory::ACTION_SUBMITTED,
                ApprovalHistory::ACTION_APPROVED,
                ApprovalHistory::ACTION_REJECTED,
                ApprovalHistory::ACTION_COMMENTED,
            ]),
            'comments' => $this->faker->optional(0.7)->sentence(),
            'changes' => [],
            'ip_address' => $this->faker->ipv4(),
            'created_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }

    /**
     * Submitted action.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => ApprovalHistory::ACTION_SUBMITTED,
            'comments' => 'Approval request submitted',
        ]);
    }

    /**
     * Approved action.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => ApprovalHistory::ACTION_APPROVED,
            'comments' => 'Approved - all criteria met.',
        ]);
    }

    /**
     * Rejected action.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => ApprovalHistory::ACTION_REJECTED,
            'comments' => 'Rejected - needs more information.',
        ]);
    }

    /**
     * Delegated action.
     */
    public function delegated(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => ApprovalHistory::ACTION_DELEGATED,
            'comments' => 'Delegated to another approver.',
        ]);
    }

    /**
     * Escalated action.
     */
    public function escalated(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => ApprovalHistory::ACTION_ESCALATED,
            'comments' => 'Escalated due to SLA breach.',
        ]);
    }
}
