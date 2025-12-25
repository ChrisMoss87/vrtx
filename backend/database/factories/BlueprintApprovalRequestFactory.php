<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlueprintApprovalRequest>
 */
class BlueprintApprovalRequestFactory extends Factory
{
    protected $model = BlueprintApprovalRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_id' => BlueprintApproval::factory(),
            'record_id' => $this->faker->numberBetween(1, 1000),
            'execution_id' => BlueprintTransitionExecution::factory(),
            'requested_by' => User::factory(),
            'original_approver_id' => null,
            'delegation_id' => null,
            'status' => BlueprintApprovalRequest::STATUS_PENDING,
            'responded_by' => null,
            'responded_at' => null,
            'comments' => null,
            'reminder_count' => 0,
            'last_reminder_at' => null,
            'escalated_at' => null,
            'escalated_from_id' => null,
        ];
    }

    /**
     * Pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintApprovalRequest::STATUS_PENDING,
            'responded_by' => null,
            'responded_at' => null,
        ]);
    }

    /**
     * Approved status.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintApprovalRequest::STATUS_APPROVED,
            'responded_by' => User::factory(),
            'responded_at' => now(),
            'comments' => 'Approved - looks good.',
        ]);
    }

    /**
     * Rejected status.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintApprovalRequest::STATUS_REJECTED,
            'responded_by' => User::factory(),
            'responded_at' => now(),
            'comments' => 'Rejected - needs more information.',
        ]);
    }

    /**
     * Expired status.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintApprovalRequest::STATUS_EXPIRED,
            'responded_at' => now(),
        ]);
    }

    /**
     * Escalated.
     */
    public function escalated(): static
    {
        return $this->state(fn (array $attributes) => [
            'escalated_at' => now(),
            'escalated_from_id' => User::factory(),
        ]);
    }

    /**
     * With reminders sent.
     */
    public function withReminders(int $count = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_count' => $count,
            'last_reminder_at' => now()->subHours(8),
        ]);
    }
}
