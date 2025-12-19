<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Playbook;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Playbook>
 */
class PlaybookFactory extends Factory
{
    protected $model = Playbook::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Enterprise Deal Playbook',
            'SMB Sales Motion',
            'Inbound Lead Qualification',
            'Customer Onboarding',
            'Renewal Process',
            'Upsell Opportunity',
            'Competitive Displacement',
            'New Logo Acquisition',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 10000),
            'description' => $this->faker->paragraph(),
            'trigger_module' => $this->faker->randomElement(['deals', 'leads', 'contacts', 'accounts']),
            'trigger_condition' => $this->faker->randomElement(['created', 'stage_changed', 'field_updated']),
            'trigger_config' => [
                'field' => 'stage',
                'operator' => 'equals',
                'value' => 'qualification',
            ],
            'estimated_days' => $this->faker->numberBetween(14, 90),
            'is_active' => $this->faker->boolean(80),
            'auto_assign' => $this->faker->boolean(50),
            'default_owner_id' => User::factory(),
            'tags' => $this->faker->randomElements(['enterprise', 'smb', 'inbound', 'outbound', 'renewal'], 2),
            'display_order' => $this->faker->numberBetween(1, 10),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Active playbook state.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive playbook state.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * For deals module.
     */
    public function forDeals(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_module' => 'deals',
            'name' => 'Deal Closing Playbook',
        ]);
    }

    /**
     * For leads module.
     */
    public function forLeads(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_module' => 'leads',
            'name' => 'Lead Qualification Playbook',
        ]);
    }

    /**
     * With phases.
     */
    public function withPhases(int $count = 3): static
    {
        return $this->has(
            \App\Models\PlaybookPhase::factory()->count($count),
            'phases'
        );
    }

    /**
     * With tasks.
     */
    public function withTasks(int $count = 5): static
    {
        return $this->has(
            \App\Models\PlaybookTask::factory()->count($count),
            'tasks'
        );
    }

    /**
     * With goals.
     */
    public function withGoals(int $count = 2): static
    {
        return $this->has(
            \App\Models\PlaybookGoal::factory()->count($count),
            'goals'
        );
    }
}
