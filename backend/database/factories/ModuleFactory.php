<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Modules\Entities\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Modules\Entities\Module>
 */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(2, true);

        return [
            'name' => ucwords($name),
            'singular_name' => ucwords($this->faker->word()),
            'api_name' => strtolower(str_replace(' ', '_', $name)) . '_' . $this->faker->unique()->numberBetween(1, 9999),
            'icon' => $this->faker->randomElement(['users', 'briefcase', 'calendar', 'phone', 'mail', 'folder']),
            'description' => $this->faker->sentence(),
            'is_active' => $this->faker->boolean(80),
            'settings' => [],
            'display_order' => $this->faker->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the module is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the module is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
