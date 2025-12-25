<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalEscalationLog>
 */
class ApprovalEscalationLogFactory extends Factory
{
    protected $model = ApprovalEscalationLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_request_id' => BlueprintApprovalRequest::factory(),
            'escalation_type' => $this->faker->randomElement([
                ApprovalEscalationLog::TYPE_REMINDER,
                ApprovalEscalationLog::TYPE_ESCALATE,
                ApprovalEscalationLog::TYPE_AUTO_REJECT,
                ApprovalEscalationLog::TYPE_REASSIGN,
            ]),
            'from_user_id' => User::factory(),
            'to_user_id' => User::factory(),
            'reason' => $this->faker->sentence(),
        ];
    }

    /**
     * Reminder type.
     */
    public function reminder(): static
    {
        return $this->state(fn (array $attributes) => [
            'escalation_type' => ApprovalEscalationLog::TYPE_REMINDER,
            'from_user_id' => null,
            'reason' => 'Approval reminder sent',
        ]);
    }

    /**
     * Escalation type.
     */
    public function escalation(): static
    {
        return $this->state(fn (array $attributes) => [
            'escalation_type' => ApprovalEscalationLog::TYPE_ESCALATE,
            'reason' => 'Escalated due to SLA breach',
        ]);
    }

    /**
     * Auto-reject type.
     */
    public function autoReject(): static
    {
        return $this->state(fn (array $attributes) => [
            'escalation_type' => ApprovalEscalationLog::TYPE_AUTO_REJECT,
            'from_user_id' => null,
            'to_user_id' => null,
            'reason' => 'Auto-rejected after 14 days without response',
        ]);
    }

    /**
     * Reassignment type.
     */
    public function reassignment(): static
    {
        return $this->state(fn (array $attributes) => [
            'escalation_type' => ApprovalEscalationLog::TYPE_REASSIGN,
            'reason' => 'Manually reassigned by administrator',
        ]);
    }
}
