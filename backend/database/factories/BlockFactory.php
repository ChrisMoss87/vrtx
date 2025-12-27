<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Modules\Entities\Block;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Block>
 */
class BlockFactory extends Factory
{
    protected $model = Block::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => fn () => DB::table('modules')->where('api_name', 'deals')->first()?->id ?? DB::table('modules')->first()?->id,
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(['section', 'tab', 'accordion', 'card']),
            'display_order' => $this->faker->numberBetween(0, 10),
            'settings' => [],
        ];
    }

    /**
     * Indicate that the block is a section.
     */
    public function section(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'section',
        ]);
    }

    /**
     * Indicate that the block is a tab.
     */
    public function tab(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'tab',
        ]);
    }
}
