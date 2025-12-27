<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Modules\Entities\Module;
use App\Domain\Pipeline\Entities\Pipeline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Pipeline\Entities\Pipeline>
 */
class PipelineFactory extends Factory
{
    protected $model = Pipeline::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true) . ' Pipeline',
            'module_id' => fn () => Module::where('api_name', 'deals')->first()?->id ?? Module::first()?->id,
            'stage_field_api_name' => 'stage_id',
            'is_active' => $this->faker->boolean(90),
            'settings' => [],
        ];
    }

    /**
     * Indicate that the pipeline is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the pipeline is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a pipeline with default stages.
     */
    public function withStages(): static
    {
        return $this->afterCreating(function (Pipeline $pipeline) {
            $stages = [
                ['name' => 'Lead', 'color' => '#3b82f6', 'probability' => 10, 'display_order' => 0],
                ['name' => 'Qualified', 'color' => '#8b5cf6', 'probability' => 25, 'display_order' => 1],
                ['name' => 'Proposal', 'color' => '#f59e0b', 'probability' => 50, 'display_order' => 2],
                ['name' => 'Negotiation', 'color' => '#f97316', 'probability' => 75, 'display_order' => 3],
                ['name' => 'Won', 'color' => '#22c55e', 'probability' => 100, 'display_order' => 4, 'is_won_stage' => true],
                ['name' => 'Lost', 'color' => '#ef4444', 'probability' => 0, 'display_order' => 5, 'is_lost_stage' => true],
            ];

            foreach ($stages as $stageData) {
                $pipeline->stages()->create($stageData);
            }
        });
    }
}
