<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalRule>
 */
class ApprovalRuleFactory extends Factory
{
    protected $model = ApprovalRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Quote Over $50k Approval',
                'Discount Over 20% Approval',
                'Contract Amendment Approval',
                'Expense Report Approval',
                'Proposal Review',
                'Credit Approval',
            ]),
            'description' => $this->faker->sentence(),
            'entity_type' => $this->faker->randomElement(ApprovalRule::ENTITY_TYPES),
            'module_id' => null,
            'conditions' => [
                ['field' => 'amount', 'operator' => '>=', 'value' => 50000],
            ],
            'approver_chain' => [
                ['user_id' => 1, 'type' => 'user'],
                ['role_id' => 2, 'type' => 'role'],
            ],
            'approval_type' => $this->faker->randomElement(ApprovalRule::APPROVAL_TYPES),
            'allow_self_approval' => false,
            'require_comments' => $this->faker->boolean(40),
            'sla_hours' => $this->faker->randomElement([24, 48, 72]),
            'escalation_rules' => [
                'escalate_after_hours' => 48,
                'escalate_to' => 'manager',
            ],
            'notification_settings' => [
                'notify_on_submit' => true,
                'notify_on_approve' => true,
                'notify_on_reject' => true,
                'send_reminders' => true,
            ],
            'is_active' => true,
            'priority' => $this->faker->numberBetween(1, 10),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Quote approval rule.
     */
    public function forQuotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Quote Approval',
            'entity_type' => ApprovalRule::ENTITY_QUOTE,
            'conditions' => [
                ['field' => 'total_amount', 'operator' => '>=', 'value' => 50000],
            ],
        ]);
    }

    /**
     * Discount approval rule.
     */
    public function forDiscounts(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Discount Approval',
            'entity_type' => ApprovalRule::ENTITY_DISCOUNT,
            'conditions' => [
                ['field' => 'discount_percentage', 'operator' => '>=', 'value' => 20],
            ],
        ]);
    }

    /**
     * Sequential approval type.
     */
    public function sequential(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_type' => ApprovalRule::TYPE_SEQUENTIAL,
        ]);
    }

    /**
     * Parallel approval type.
     */
    public function parallel(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_type' => ApprovalRule::TYPE_PARALLEL,
        ]);
    }

    /**
     * Active rule.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive rule.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
