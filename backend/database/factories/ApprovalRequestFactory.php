<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'rule_id' => ApprovalRule::factory(),
            'entity_type' => $this->faker->randomElement(['quote', 'proposal', 'discount', 'contract']),
            'entity_id' => $this->faker->numberBetween(1, 1000),
            'title' => $this->faker->randomElement([
                'Quote Approval - Acme Corp',
                'Discount Request - 25% off',
                'Contract Amendment - TechCo',
                'Proposal Review - Enterprise Deal',
            ]),
            'description' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(ApprovalRequest::STATUSES),
            'snapshot_data' => [
                'amount' => $this->faker->randomFloat(2, 10000, 500000),
                'customer_name' => $this->faker->company(),
                'submitted_by' => $this->faker->name(),
            ],
            'value' => $this->faker->randomFloat(2, 10000, 500000),
            'currency' => 'USD',
            'submitted_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'completed_at' => null,
            'expires_at' => $this->faker->optional(0.5)->dateTimeBetween('now', '+7 days'),
            'requested_by' => User::factory(),
            'final_approver_id' => null,
            'final_comments' => null,
        ];
    }

    /**
     * Pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalRequest::STATUS_PENDING,
            'completed_at' => null,
        ]);
    }

    /**
     * In progress status.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalRequest::STATUS_IN_PROGRESS,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Approved status.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalRequest::STATUS_APPROVED,
            'completed_at' => now(),
            'final_approver_id' => User::factory(),
            'final_comments' => 'Approved - all requirements met.',
        ]);
    }

    /**
     * Rejected status.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalRequest::STATUS_REJECTED,
            'completed_at' => now(),
            'final_approver_id' => User::factory(),
            'final_comments' => 'Rejected - needs more documentation.',
        ]);
    }

    /**
     * Cancelled status.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalRequest::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Expired status.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalRequest::STATUS_EXPIRED,
            'completed_at' => now(),
            'expires_at' => now()->subDays(1),
        ]);
    }

    /**
     * With steps.
     */
    public function withSteps(int $count = 3): static
    {
        return $this->has(
            \App\Models\ApprovalStep::factory()->count($count),
            'steps'
        );
    }
}
