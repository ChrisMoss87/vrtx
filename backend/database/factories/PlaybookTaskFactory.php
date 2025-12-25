<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Playbook;
use App\Infrastructure\Persistence\Eloquent\Models\PlaybookPhase;
use App\Infrastructure\Persistence\Eloquent\Models\PlaybookTask;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\PlaybookTask>
 */
class PlaybookTaskFactory extends Factory
{
    protected $model = PlaybookTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'playbook_id' => Playbook::factory(),
            'phase_id' => null,
            'title' => $this->faker->randomElement([
                'Schedule discovery call',
                'Send intro email',
                'Identify decision makers',
                'Complete BANT qualification',
                'Prepare demo environment',
                'Deliver product demo',
                'Send proposal',
                'Review contract terms',
                'Negotiate pricing',
                'Get final approval',
                'Send contract for signature',
                'Schedule kickoff meeting',
            ]),
            'description' => $this->faker->paragraph(),
            'task_type' => $this->faker->randomElement(['manual', 'automated', 'approval']),
            'task_config' => [],
            'due_days' => $this->faker->numberBetween(1, 14),
            'duration_estimate' => $this->faker->numberBetween(15, 120),
            'is_required' => $this->faker->boolean(70),
            'is_milestone' => $this->faker->boolean(20),
            'assignee_type' => $this->faker->randomElement(['owner', 'specific', 'role']),
            'assignee_id' => null,
            'assignee_role' => $this->faker->randomElement(['sales_rep', 'sales_manager', 'solutions_engineer']),
            'dependencies' => [],
            'checklist' => [
                'Review account history',
                'Research company news',
                'Prepare talking points',
                'Update CRM notes',
            ],
            'resources' => [
                ['type' => 'document', 'name' => 'Sales Playbook', 'url' => '/docs/sales-playbook.pdf'],
                ['type' => 'video', 'name' => 'Demo Best Practices', 'url' => '/videos/demo-guide.mp4'],
            ],
            'display_order' => $this->faker->numberBetween(1, 20),
        ];
    }

    /**
     * Required task.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }

    /**
     * Milestone task.
     */
    public function milestone(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_milestone' => true,
            'is_required' => true,
        ]);
    }

    /**
     * Automated task.
     */
    public function automated(): static
    {
        return $this->state(fn (array $attributes) => [
            'task_type' => 'automated',
            'task_config' => [
                'action' => 'send_email',
                'template_id' => 1,
            ],
        ]);
    }

    /**
     * Approval task.
     */
    public function approval(): static
    {
        return $this->state(fn (array $attributes) => [
            'task_type' => 'approval',
            'task_config' => [
                'approver_role' => 'sales_manager',
                'escalation_hours' => 24,
            ],
        ]);
    }

    /**
     * With specific assignee.
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assignee_type' => 'specific',
            'assignee_id' => $user->id,
        ]);
    }
}
