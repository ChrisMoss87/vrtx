<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Approval\Entities\ApprovalStep;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Approval\Entities\ApprovalStep>
 */
class ApprovalStepFactory extends Factory
{
    protected $model = ApprovalStep::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => ApprovalRequest::factory(),
            'approver_id' => User::factory(),
            'role_id' => null,
            'approver_type' => ApprovalStep::TYPE_USER,
            'step_order' => $this->faker->numberBetween(1, 5),
            'status' => ApprovalStep::STATUS_PENDING,
            'comments' => null,
            'notified_at' => null,
            'viewed_at' => null,
            'decided_at' => null,
            'due_at' => $this->faker->optional(0.7)->dateTimeBetween('now', '+3 days'),
            'is_current' => false,
            'delegated_to_id' => null,
            'delegated_by_id' => null,
        ];
    }

    /**
     * Pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalStep::STATUS_PENDING,
            'decided_at' => null,
        ]);
    }

    /**
     * Approved status.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalStep::STATUS_APPROVED,
            'decided_at' => now(),
            'comments' => 'Approved.',
        ]);
    }

    /**
     * Rejected status.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalStep::STATUS_REJECTED,
            'decided_at' => now(),
            'comments' => 'Rejected - needs revision.',
        ]);
    }

    /**
     * Skipped status.
     */
    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalStep::STATUS_SKIPPED,
            'decided_at' => now(),
            'comments' => 'Skipped by system.',
        ]);
    }

    /**
     * Current step.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_current' => true,
            'notified_at' => now(),
        ]);
    }

    /**
     * Overdue step.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalStep::STATUS_PENDING,
            'is_current' => true,
            'due_at' => now()->subDays(2),
        ]);
    }

    /**
     * Delegated step.
     */
    public function delegated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalStep::STATUS_DELEGATED,
            'delegated_to_id' => User::factory(),
            'delegated_by_id' => User::factory(),
        ]);
    }

    /**
     * Manager approver type.
     */
    public function managerApprover(): static
    {
        return $this->state(fn (array $attributes) => [
            'approver_type' => ApprovalStep::TYPE_MANAGER,
            'approver_id' => null,
        ]);
    }

    /**
     * Role-based approver type.
     */
    public function roleApprover(int $roleId = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'approver_type' => ApprovalStep::TYPE_ROLE,
            'approver_id' => null,
            'role_id' => $roleId,
        ]);
    }
}
