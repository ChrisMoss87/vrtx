<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Blueprint\Entities\BlueprintApproval;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Blueprint\Entities\BlueprintApproval>
 */
class BlueprintApprovalFactory extends Factory
{
    protected $model = BlueprintApproval::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transition_id' => BlueprintTransition::factory(),
            'approval_type' => $this->faker->randomElement([
                BlueprintApproval::TYPE_SPECIFIC_USERS,
                BlueprintApproval::TYPE_ROLE_BASED,
                BlueprintApproval::TYPE_MANAGER,
            ]),
            'config' => ['user_ids' => [1, 2]],
            'require_all' => $this->faker->boolean(30),
            'auto_reject_days' => $this->faker->optional(0.3)->numberBetween(3, 14),
            'escalation_hours' => $this->faker->optional(0.5)->numberBetween(24, 72),
            'escalation_type' => $this->faker->optional(0.5)->randomElement([
                BlueprintApproval::ESCALATION_MANAGER,
                BlueprintApproval::ESCALATION_SPECIFIC_USER,
                BlueprintApproval::ESCALATION_ROLE,
            ]),
            'escalation_config' => [],
            'reminder_hours' => $this->faker->optional(0.5)->numberBetween(4, 24),
            'max_reminders' => $this->faker->numberBetween(1, 5),
            'notify_on_pending' => true,
            'notify_on_complete' => true,
        ];
    }

    /**
     * Specific users approval.
     */
    public function specificUsers(array $userIds): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_type' => BlueprintApproval::TYPE_SPECIFIC_USERS,
            'config' => ['user_ids' => $userIds],
        ]);
    }

    /**
     * Role-based approval.
     */
    public function roleBased(array $roleIds): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_type' => BlueprintApproval::TYPE_ROLE_BASED,
            'config' => ['role_ids' => $roleIds],
        ]);
    }

    /**
     * Manager approval.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_type' => BlueprintApproval::TYPE_MANAGER,
            'config' => [],
        ]);
    }

    /**
     * With escalation.
     */
    public function withEscalation(): static
    {
        return $this->state(fn (array $attributes) => [
            'escalation_hours' => 24,
            'escalation_type' => BlueprintApproval::ESCALATION_MANAGER,
        ]);
    }

    /**
     * With reminders.
     */
    public function withReminders(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_hours' => 8,
            'max_reminders' => 3,
        ]);
    }
}
