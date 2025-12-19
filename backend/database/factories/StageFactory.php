<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Pipeline;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stage>
 */
class StageFactory extends Factory
{
    protected $model = Stage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pipeline_id' => Pipeline::factory(),
            'name' => $this->faker->words(2, true),
            'color' => $this->faker->hexColor(),
            'probability' => $this->faker->numberBetween(0, 100),
            'display_order' => $this->faker->numberBetween(0, 10),
            'is_won_stage' => false,
            'is_lost_stage' => false,
            'settings' => [],
        ];
    }

    /**
     * Indicate that this is a won stage.
     */
    public function won(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_won_stage' => true,
            'is_lost_stage' => false,
            'probability' => 100,
            'color' => '#22c55e',
        ]);
    }

    /**
     * Indicate that this is a lost stage.
     */
    public function lost(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_won_stage' => false,
            'is_lost_stage' => true,
            'probability' => 0,
            'color' => '#ef4444',
        ]);
    }
}
