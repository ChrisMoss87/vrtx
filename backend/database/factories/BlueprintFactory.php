<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Blueprint>
 */
class BlueprintFactory extends Factory
{
    protected $model = Blueprint::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true) . ' Blueprint',
            'module_id' => fn () => DB::table('modules')->where('api_name', 'deals')->first()?->id ?? DB::table('modules')->first()?->id,
            'field_id' => Field::factory(),
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'layout_data' => [],
        ];
    }

    /**
     * Indicate that the blueprint is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the blueprint is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create blueprint with default approval workflow states.
     */
    public function withApprovalWorkflow(): static
    {
        return $this->afterCreating(function (Blueprint $blueprint) {
            $draft = $blueprint->states()->create([
                'name' => 'Draft',
                'color' => '#94a3b8',
                'is_initial' => true,
                'is_terminal' => false,
                'display_order' => 0,
            ]);

            $pending = $blueprint->states()->create([
                'name' => 'Pending Approval',
                'color' => '#f59e0b',
                'is_initial' => false,
                'is_terminal' => false,
                'display_order' => 1,
            ]);

            $approved = $blueprint->states()->create([
                'name' => 'Approved',
                'color' => '#22c55e',
                'is_initial' => false,
                'is_terminal' => true,
                'display_order' => 2,
            ]);

            $rejected = $blueprint->states()->create([
                'name' => 'Rejected',
                'color' => '#ef4444',
                'is_initial' => false,
                'is_terminal' => true,
                'display_order' => 3,
            ]);

            // Create transitions
            $blueprint->transitions()->create([
                'name' => 'Submit for Approval',
                'from_state_id' => $draft->id,
                'to_state_id' => $pending->id,
                'display_order' => 0,
            ]);

            $blueprint->transitions()->create([
                'name' => 'Approve',
                'from_state_id' => $pending->id,
                'to_state_id' => $approved->id,
                'display_order' => 1,
            ]);

            $blueprint->transitions()->create([
                'name' => 'Reject',
                'from_state_id' => $pending->id,
                'to_state_id' => $rejected->id,
                'display_order' => 2,
            ]);

            $blueprint->transitions()->create([
                'name' => 'Resubmit',
                'from_state_id' => $rejected->id,
                'to_state_id' => $pending->id,
                'display_order' => 3,
            ]);
        });
    }

    /**
     * Set layout data.
     */
    public function withLayout(array $layout): static
    {
        return $this->state(fn (array $attributes) => [
            'layout_data' => $layout,
        ]);
    }
}
