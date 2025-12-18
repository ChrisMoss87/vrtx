<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Module;
use App\Models\ModuleView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModuleView>
 */
class ModuleViewFactory extends Factory
{
    protected $model = ModuleView::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => fn () => Module::where('api_name', 'deals')->first()?->id ?? Module::first()?->id,
            'user_id' => User::factory(),
            'name' => fake()->words(2, true) . ' View',
            'description' => fake()->optional()->sentence(),
            'filters' => [],
            'sorting' => [],
            'column_visibility' => [],
            'column_order' => [],
            'column_widths' => [],
            'page_size' => fake()->randomElement([25, 50, 100]),
            'is_default' => false,
            'is_shared' => false,
            'display_order' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Mark as default view.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Mark as shared view.
     */
    public function shared(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_shared' => true,
        ]);
    }

    /**
     * Add sample filters.
     */
    public function withFilters(): static
    {
        return $this->state(fn (array $attributes) => [
            'filters' => [
                [
                    'field' => 'status',
                    'operator' => 'equals',
                    'value' => 'active',
                ],
                [
                    'field' => 'created_at',
                    'operator' => 'after',
                    'value' => now()->subMonth()->toDateString(),
                ],
            ],
        ]);
    }

    /**
     * Add sample sorting.
     */
    public function withSorting(): static
    {
        return $this->state(fn (array $attributes) => [
            'sorting' => [
                ['field' => 'created_at', 'direction' => 'desc'],
            ],
        ]);
    }

    /**
     * Add column configuration.
     */
    public function withColumns(array $columns): static
    {
        return $this->state(fn (array $attributes) => [
            'column_visibility' => array_fill_keys($columns, true),
            'column_order' => $columns,
        ]);
    }

    /**
     * Attach to specific module.
     */
    public function forModule(Module $module): static
    {
        return $this->state(fn (array $attributes) => [
            'module_id' => $module->id,
        ]);
    }

    /**
     * Attach to specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
